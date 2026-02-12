<?php

namespace Aicl\Rlm;

use Aicl\Models\GenerationTrace;
use Aicl\Models\GoldenAnnotation;
use Aicl\Models\PreventionRule;
use Aicl\Models\RlmFailure;
use Aicl\Models\RlmLesson;
use Aicl\Models\RlmPattern;
use Aicl\Models\RlmScore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Central query interface for the RLM knowledge system.
 *
 * Coordinates between Elasticsearch (search/discovery) and PostgreSQL (storage/hydration).
 * Replaces the SQLite-based KnowledgeBase with a two-layer architecture:
 * - PG: source of truth for all CRUD operations
 * - ES: hybrid search (kNN + BM25 + RRF) for discovery
 *
 * Graceful degradation:
 * | ES Status   | Embedding Status | Search Behavior                            |
 * |-------------|------------------|--------------------------------------------|
 * | Available   | Available        | Full hybrid: kNN + BM25 + deterministic    |
 * | Available   | Unavailable      | BM25 text search + deterministic (no kNN)  |
 * | Unavailable | Any              | Deterministic only (Eloquent scopes, LIKE)  |
 */
class KnowledgeService
{
    private ?bool $esAvailable = null;

    public function __construct(
        private EmbeddingService $embeddingService,
    ) {}

    // ─── Search ─────────────────────────────────────────────────

    /**
     * Hybrid search across all knowledge types.
     *
     * Flow: generate embedding → ES hybrid query (kNN + BM25 + RRF) → PG hydration.
     * Falls back through degradation tiers when ES or embeddings are unavailable.
     *
     * @return Collection<int, Model>
     */
    public function search(string $query, ?string $type = null, int $limit = 10): Collection
    {
        $types = $this->resolveSearchTypes($type);

        if ($this->isElasticsearchAvailable()) {
            return $this->searchViaElasticsearch($query, $types, $limit);
        }

        return $this->searchViaDeterministic($query, $types, $limit);
    }

    // ─── Recall ─────────────────────────────────────────────────

    /**
     * Agent-facing context retrieval with risk briefing.
     *
     * Combines ES hybrid search with deterministic topic lookups,
     * prevention rules, recent outcomes, and golden annotations.
     *
     * @param  array<string, mixed>  $entityContext
     * @return array{failures: Collection, lessons: Collection, scores: Collection, prevention_rules: Collection, golden_annotations: Collection, risk_briefing: array}
     */
    public function recall(string $agent, int $phase, ?array $entityContext = null, ?string $entityName = null): array
    {
        $contextString = $this->buildContextString($agent, $phase, $entityContext);
        $topicMap = $this->getTopicsForAgentPhase($agent, $phase);

        // 1. Search for failures via ES or deterministic fallback
        $failures = $this->searchFailuresForRecall($contextString, $entityContext);

        // 2. Deterministic topic-based lessons + search-based lessons
        $lessons = $this->searchLessonsForRecall($contextString, $topicMap);

        // 3. Prevention rules matching entity context
        $preventionRules = $this->getPreventionRulesForContext($entityContext);

        // 4. Scores for this entity (PG direct)
        $scores = collect();
        if ($entityName !== null) {
            $scores = RlmScore::query()
                ->forEntity($entityName)
                ->latest()
                ->limit(10)
                ->get();
        }

        // 5. Golden annotations (3-layer retrieval)
        $goldenAnnotations = $this->getGoldenAnnotationsForRecall($contextString, $entityContext);

        // 6. Recent outcomes for similar entities
        $recentOutcomes = $this->getRecentOutcomes($entityContext);

        // 7. Build risk briefing
        $riskBriefing = $this->buildRiskBriefing($failures, $preventionRules, $recentOutcomes, $entityContext);

        return [
            'failures' => $failures,
            'lessons' => $lessons,
            'scores' => $scores,
            'prevention_rules' => $preventionRules,
            'golden_annotations' => $goldenAnnotations,
            'risk_briefing' => $riskBriefing,
        ];
    }

    // ─── Write Methods ──────────────────────────────────────────

    /**
     * Add a lesson to the knowledge base.
     *
     * Scout auto-indexes → observer dispatches embedding job.
     */
    public function addLesson(
        string $topic,
        string $summary,
        string $detail,
        ?string $subtopic = null,
        ?string $tags = null,
        ?string $source = null,
        float $confidence = 1.0,
    ): RlmLesson {
        return RlmLesson::query()->create([
            'topic' => $topic,
            'subtopic' => $subtopic,
            'summary' => $summary,
            'detail' => $detail,
            'tags' => $tags,
            'source' => $source,
            'confidence' => $confidence,
            'is_verified' => false,
            'is_active' => true,
            'owner_id' => $this->getDefaultOwnerId(),
        ]);
    }

    /**
     * Record a failure. Upserts by failure_code.
     *
     * @param  array<string, mixed>  $data
     */
    public function recordFailure(array $data): RlmFailure
    {
        $failureCode = $data['failure_code'] ?? $data['failure_id'] ?? null;

        if ($failureCode === null) {
            throw new \InvalidArgumentException('failure_code is required');
        }

        return RlmFailure::query()->updateOrCreate(
            ['failure_code' => $failureCode],
            array_merge($data, [
                'failure_code' => $failureCode,
                'owner_id' => $data['owner_id'] ?? $this->getDefaultOwnerId(),
            ]),
        );
    }

    /**
     * Record a validation score.
     *
     * @param  array<string, mixed>|null  $details
     */
    public function recordScore(
        string $entityName,
        string $type,
        int $passed,
        int $total,
        float $percentage,
        int $errors = 0,
        int $warnings = 0,
        ?array $details = null,
    ): RlmScore {
        return RlmScore::query()->create([
            'entity_name' => $entityName,
            'score_type' => $type,
            'passed' => $passed,
            'total' => $total,
            'percentage' => $percentage,
            'errors' => $errors,
            'warnings' => $warnings,
            'details' => $details,
            'owner_id' => $this->getDefaultOwnerId(),
        ]);
    }

    /**
     * Record a generation trace.
     *
     * @param  array<string, mixed>  $data
     */
    public function recordTrace(string $entityName, array $data): GenerationTrace
    {
        return GenerationTrace::query()->create(array_merge($data, [
            'entity_name' => $entityName,
            'owner_id' => $data['owner_id'] ?? $this->getDefaultOwnerId(),
        ]));
    }

    // ─── Query Methods ──────────────────────────────────────────

    /**
     * Get failures matching an entity context via JSONB queries.
     *
     * @param  array<string, mixed>  $context
     * @param  array<int, string>|null  $severities
     * @return Collection<int, RlmFailure>
     */
    public function getFailuresByContext(array $context = [], ?array $severities = null): Collection
    {
        $query = RlmFailure::query()->where('is_active', true);

        if ($context !== []) {
            $query->where(function ($q) use ($context) {
                // Include failures with null entity_context (universal)
                $q->whereNull('entity_context');

                foreach ($context as $key => $value) {
                    $q->orWhereJsonContains('entity_context->'.$key, $value);
                }
            });
        }

        if ($severities !== null && $severities !== []) {
            $query->whereIn('severity', $severities);
        }

        return $query->orderByRaw("CASE severity
            WHEN 'critical' THEN 1
            WHEN 'high' THEN 2
            WHEN 'medium' THEN 3
            WHEN 'low' THEN 4
            ELSE 5
        END")->get();
    }

    /**
     * Get a single failure by its failure_code.
     */
    public function getFailure(string $failureCode): ?RlmFailure
    {
        return RlmFailure::query()
            ->where('failure_code', $failureCode)
            ->first();
    }

    /**
     * Aggregate statistics across all knowledge tables.
     *
     * @return array<string, mixed>
     */
    public function stats(): array
    {
        $patternCount = RlmPattern::query()->where('is_active', true)->count();
        $failureTotal = RlmFailure::query()->count();
        $failureActive = RlmFailure::query()->where('is_active', true)->count();
        $lessonCount = RlmLesson::query()->count();
        $lessonVerified = RlmLesson::query()->where('is_verified', true)->count();
        $scoreEntities = RlmScore::query()->distinct('entity_name')->count('entity_name');
        $traceCount = GenerationTrace::query()->count();
        $preventionRuleCount = PreventionRule::query()->where('is_active', true)->count();
        $goldenAnnotationCount = GoldenAnnotation::query()->where('is_active', true)->count();

        $lessonsByTopic = RlmLesson::query()
            ->selectRaw('topic, COUNT(*) as count')
            ->groupBy('topic')
            ->orderByDesc('count')
            ->pluck('count', 'topic')
            ->all();

        $topFailingPatterns = RlmFailure::query()
            ->where('is_active', true)
            ->orderByDesc('report_count')
            ->limit(5)
            ->get(['failure_code', 'title', 'report_count', 'severity']);

        return [
            'storage' => 'postgresql',
            'search_engine' => $this->isElasticsearchAvailable() ? 'elasticsearch' : 'database',
            'embeddings' => $this->embeddingService->isAvailable() ? 'active' : 'unavailable',
            'patterns' => $patternCount,
            'failures' => [
                'total' => $failureTotal,
                'active' => $failureActive,
            ],
            'lessons' => [
                'total' => $lessonCount,
                'verified' => $lessonVerified,
                'unverified' => $lessonCount - $lessonVerified,
                'by_topic' => $lessonsByTopic,
            ],
            'scores' => $scoreEntities,
            'traces' => $traceCount,
            'prevention_rules' => $preventionRuleCount,
            'golden_annotations' => $goldenAnnotationCount,
            'top_failing' => $topFailingPatterns->toArray(),
        ];
    }

    /**
     * Check if Elasticsearch is reachable.
     */
    public function isElasticsearchAvailable(): bool
    {
        if ($this->esAvailable !== null) {
            return $this->esAvailable;
        }

        try {
            $response = Http::timeout(2)->get($this->getEsBaseUrl());
            $this->esAvailable = $response->successful();
        } catch (\Throwable) {
            $this->esAvailable = false;
        }

        return $this->esAvailable;
    }

    /**
     * Check if the full search stack is available (ES + embeddings).
     */
    public function isSearchAvailable(): bool
    {
        return $this->isElasticsearchAvailable() && $this->embeddingService->isAvailable();
    }

    /**
     * Reset cached ES availability (for testing or reconnection).
     */
    public function resetAvailabilityCache(): void
    {
        $this->esAvailable = null;
    }

    // ─── Elasticsearch Search ───────────────────────────────────

    /**
     * @param  array<int, string>  $types
     * @return Collection<int, Model>
     */
    private function searchViaElasticsearch(string $query, array $types, int $limit): Collection
    {
        $embedding = $this->embeddingService->isAvailable()
            ? $this->embeddingService->generate($query)
            : null;

        $results = collect();

        foreach ($types as $type) {
            $indexName = $this->getIndexNameForType($type);
            $modelClass = $this->getModelClassForType($type);

            if ($indexName === null || $modelClass === null) {
                continue;
            }

            $esResults = $this->executeEsSearch($indexName, $query, $embedding, $limit);

            if ($esResults === null) {
                // ES query failed — fall back to deterministic for this type
                $results = $results->merge($this->deterministicSearchForType($query, $type, $limit));

                continue;
            }

            $ids = collect($esResults)->pluck('_id')->all();
            $scores = collect($esResults)->pluck('_score', '_id')->all();

            if ($ids !== []) {
                $models = $modelClass::query()->whereIn('id', $ids)->get();

                // Attach ES relevance score as a dynamic attribute
                $models->each(function (Model $model) use ($scores) {
                    $model->setAttribute('_relevance', $scores[$model->getKey()] ?? 0.0);
                    $model->setAttribute('_type', class_basename($model));
                });

                $results = $results->merge($models);
            }
        }

        return $results->sortByDesc('_relevance')->take($limit)->values();
    }

    /**
     * Execute an ES hybrid search (kNN + BM25 with RRF).
     *
     * @param  array<int, float>|null  $embedding
     * @return array<int, array{_id: string, _score: float}>|null Returns null on failure
     */
    private function executeEsSearch(string $index, string $query, ?array $embedding, int $limit): ?array
    {
        $body = $this->buildEsSearchBody($index, $query, $embedding, $limit);

        try {
            $response = Http::timeout(5)
                ->post("{$this->getEsBaseUrl()}/{$index}/_search", $body);

            if (! $response->successful()) {
                Log::warning("KnowledgeService: ES search failed for {$index}", [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            }

            $data = $response->json();

            return collect($data['hits']['hits'] ?? [])
                ->map(fn (array $hit): array => [
                    '_id' => $hit['_id'],
                    '_score' => $hit['_score'] ?? 0.0,
                ])
                ->all();
        } catch (\Throwable $e) {
            Log::warning('KnowledgeService: ES search exception', [
                'index' => $index,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Build the ES search body with optional kNN + BM25 using RRF.
     *
     * @param  array<int, float>|null  $embedding
     * @return array<string, mixed>
     */
    private function buildEsSearchBody(string $index, string $query, ?array $embedding, int $limit): array
    {
        $textFields = $this->getTextFieldsForIndex($index);

        if ($embedding !== null) {
            // Full hybrid: kNN + BM25 + RRF ranking
            return [
                'size' => $limit,
                'retriever' => [
                    'rrf' => [
                        'retrievers' => [
                            [
                                'standard' => [
                                    'query' => [
                                        'multi_match' => [
                                            'query' => $query,
                                            'fields' => $textFields,
                                            'type' => 'best_fields',
                                            'fuzziness' => 'AUTO',
                                        ],
                                    ],
                                ],
                            ],
                            [
                                'knn' => [
                                    'field' => 'embedding',
                                    'query_vector' => $embedding,
                                    'k' => $limit,
                                    'num_candidates' => min($limit * 10, 100),
                                ],
                            ],
                        ],
                    ],
                ],
                '_source' => false,
            ];
        }

        // BM25 only (no embedding available)
        return [
            'size' => $limit,
            'query' => [
                'multi_match' => [
                    'query' => $query,
                    'fields' => $textFields,
                    'type' => 'best_fields',
                    'fuzziness' => 'AUTO',
                ],
            ],
            '_source' => false,
        ];
    }

    // ─── Deterministic Search ───────────────────────────────────

    /**
     * @param  array<int, string>  $types
     * @return Collection<int, Model>
     */
    private function searchViaDeterministic(string $query, array $types, int $limit): Collection
    {
        $results = collect();

        foreach ($types as $type) {
            $results = $results->merge($this->deterministicSearchForType($query, $type, $limit));
        }

        return $results->take($limit)->values();
    }

    /**
     * @return Collection<int, Model>
     */
    private function deterministicSearchForType(string $query, string $type, int $limit): Collection
    {
        $likeQuery = '%'.mb_strtolower($query).'%';

        return match ($type) {
            'failure' => RlmFailure::query()
                ->where('is_active', true)
                ->where(function ($q) use ($likeQuery) {
                    $q->whereRaw('LOWER(title) LIKE ?', [$likeQuery])
                        ->orWhereRaw('LOWER(description) LIKE ?', [$likeQuery])
                        ->orWhereRaw('LOWER(failure_code) LIKE ?', [$likeQuery])
                        ->orWhereRaw('LOWER(root_cause) LIKE ?', [$likeQuery]);
                })
                ->limit($limit)
                ->get(),
            'lesson' => RlmLesson::query()
                ->where('is_active', true)
                ->where(function ($q) use ($likeQuery) {
                    $q->whereRaw('LOWER(topic) LIKE ?', [$likeQuery])
                        ->orWhereRaw('LOWER(summary) LIKE ?', [$likeQuery])
                        ->orWhereRaw('LOWER(detail) LIKE ?', [$likeQuery])
                        ->orWhereRaw('LOWER(tags) LIKE ?', [$likeQuery]);
                })
                ->limit($limit)
                ->get(),
            'pattern' => RlmPattern::query()
                ->where('is_active', true)
                ->where(function ($q) use ($likeQuery) {
                    $q->whereRaw('LOWER(name) LIKE ?', [$likeQuery])
                        ->orWhereRaw('LOWER(description) LIKE ?', [$likeQuery])
                        ->orWhereRaw('LOWER(target) LIKE ?', [$likeQuery]);
                })
                ->limit($limit)
                ->get(),
            'prevention_rule' => PreventionRule::query()
                ->where('is_active', true)
                ->whereRaw('LOWER(rule_text) LIKE ?', [$likeQuery])
                ->limit($limit)
                ->get(),
            'golden_annotation' => GoldenAnnotation::query()
                ->where('is_active', true)
                ->where(function ($q) use ($likeQuery) {
                    $q->whereRaw('LOWER(annotation_text) LIKE ?', [$likeQuery])
                        ->orWhereRaw('LOWER(annotation_key) LIKE ?', [$likeQuery])
                        ->orWhereRaw('LOWER(pattern_name) LIKE ?', [$likeQuery]);
                })
                ->limit($limit)
                ->get(),
            default => collect(),
        };
    }

    // ─── Recall Helpers ─────────────────────────────────────────

    /**
     * @param  array<string, mixed>|null  $entityContext
     * @return Collection<int, RlmFailure>
     */
    private function searchFailuresForRecall(string $contextString, ?array $entityContext): Collection
    {
        // Try ES search first
        if ($this->isElasticsearchAvailable()) {
            $embedding = $this->embeddingService->isAvailable()
                ? $this->embeddingService->generate($contextString)
                : null;

            $esResults = $this->executeEsSearch('aicl_rlm_failures', $contextString, $embedding, 20);

            if ($esResults !== null) {
                $ids = collect($esResults)->pluck('_id')->all();
                $scores = collect($esResults)->pluck('_score', '_id')->all();

                $esFailures = $ids !== []
                    ? RlmFailure::query()->whereIn('id', $ids)->get()
                    : collect();

                $esFailures->each(function (RlmFailure $f) use ($scores) {
                    $f->setAttribute('_relevance', $scores[$f->id] ?? 0.0);
                });

                // Also add deterministic context matches not found by ES
                $contextFailures = $entityContext !== null
                    ? $this->getFailuresByContext($entityContext)
                    : collect();

                return $esFailures->merge($contextFailures)->unique('id')->values();
            }
        }

        // Deterministic fallback
        return $entityContext !== null
            ? $this->getFailuresByContext($entityContext)
            : RlmFailure::query()->where('is_active', true)->limit(20)->get();
    }

    /**
     * @param  array<int, string>  $topicMap
     * @return Collection<int, RlmLesson>
     */
    private function searchLessonsForRecall(string $contextString, array $topicMap): Collection
    {
        // Deterministic: topic-based lessons
        $topicLessons = collect();
        foreach ($topicMap as $topic) {
            $topicLessons = $topicLessons->merge(
                RlmLesson::query()->byTopic($topic)->where('is_active', true)->get(),
            );
        }

        // ES search for broader matches
        if ($this->isElasticsearchAvailable()) {
            $embedding = $this->embeddingService->isAvailable()
                ? $this->embeddingService->generate($contextString)
                : null;

            $esResults = $this->executeEsSearch('aicl_rlm_lessons', $contextString, $embedding, 15);

            if ($esResults !== null) {
                $ids = collect($esResults)->pluck('_id')->all();

                $esLessons = $ids !== []
                    ? RlmLesson::query()->whereIn('id', $ids)->get()
                    : collect();

                return $topicLessons->merge($esLessons)->unique('id')->values();
            }
        }

        return $topicLessons->values();
    }

    /**
     * Get prevention rules matching entity context.
     *
     * @param  array<string, mixed>|null  $entityContext
     * @return Collection<int, PreventionRule>
     */
    private function getPreventionRulesForContext(?array $entityContext): Collection
    {
        $query = PreventionRule::query()
            ->where('is_active', true)
            ->orderByDesc('confidence')
            ->orderByDesc('priority');

        if ($entityContext !== null && $entityContext !== []) {
            $query->where(function ($q) use ($entityContext) {
                $q->whereNull('trigger_context');
                foreach ($entityContext as $key => $value) {
                    $q->orWhereJsonContains("trigger_context->{$key}", $value);
                }
            });
        }

        return $query->limit(20)->get();
    }

    // ─── Golden Annotation Retrieval ────────────────────────────

    /**
     * 3-layer retrieval: deterministic feature tags → BM25 → kNN.
     *
     * @param  array<string, mixed>|null  $entityContext
     * @return Collection<int, GoldenAnnotation>
     */
    private function getGoldenAnnotationsForRecall(string $contextString, ?array $entityContext): Collection
    {
        // Layer 1: Deterministic — filter by feature tags
        $featureTags = $this->extractFeatureTags($entityContext);
        $featureTags[] = 'universal'; // Always include universal patterns

        $deterministicAnnotations = GoldenAnnotation::query()
            ->where('is_active', true)
            ->where(function ($q) use ($featureTags) {
                foreach ($featureTags as $tag) {
                    $q->orWhereJsonContains('feature_tags', $tag);
                }
            })
            ->get();

        // Layer 2+3: ES search (BM25 + kNN) for broader matches
        if ($this->isElasticsearchAvailable()) {
            $embedding = $this->embeddingService->isAvailable()
                ? $this->embeddingService->generate($contextString)
                : null;

            $esResults = $this->executeEsSearch('aicl_golden_annotations', $contextString, $embedding, 20);

            if ($esResults !== null) {
                $ids = collect($esResults)->pluck('_id')->all();

                $esAnnotations = $ids !== []
                    ? GoldenAnnotation::query()->whereIn('id', $ids)->get()
                    : collect();

                return $deterministicAnnotations->merge($esAnnotations)->unique('id')->values();
            }
        }

        return $deterministicAnnotations->values();
    }

    /**
     * Extract feature tags from entity context for golden annotation matching.
     *
     * @param  array<string, mixed>|null  $entityContext
     * @return array<int, string>
     */
    private function extractFeatureTags(?array $entityContext): array
    {
        if ($entityContext === null || $entityContext === []) {
            return [];
        }

        $tagMap = [
            'has_states' => 'states',
            'has_media' => 'media',
            'has_enum' => 'enum',
            'has_pdf' => 'pdf',
            'has_notifications' => 'notifications',
            'has_tagging' => 'tagging',
            'has_search' => 'search',
            'has_audit' => 'audit',
            'has_api' => 'api',
            'has_widgets' => 'widgets',
        ];

        $tags = [];
        foreach ($entityContext as $key => $value) {
            if ($value && isset($tagMap[$key])) {
                $tags[] = $tagMap[$key];
            }
        }

        return $tags;
    }

    // ─── Risk Briefing ──────────────────────────────────────────

    /**
     * Build structured risk briefing from failures, prevention rules, and recent outcomes.
     *
     * @param  Collection<int, RlmFailure>  $failures
     * @param  Collection<int, PreventionRule>  $preventionRules
     * @param  Collection<int, GenerationTrace>  $recentOutcomes
     * @param  array<string, mixed>|null  $entityContext
     * @return array{high_risk: array, prevention_rules: array, recent_outcomes: array}
     */
    private function buildRiskBriefing(
        Collection $failures,
        Collection $preventionRules,
        Collection $recentOutcomes,
        ?array $entityContext,
    ): array {
        // High-risk failures sorted by relevance
        $highRisk = $failures
            ->sortByDesc(fn (RlmFailure $f) => $f->getAttribute('_relevance') ?? $f->report_count)
            ->take(10)
            ->map(fn (RlmFailure $f): array => [
                'failure_code' => $f->failure_code,
                'title' => $f->title,
                'severity' => $f->severity?->value ?? (string) $f->severity,
                'relevance' => $f->getAttribute('_relevance') ?? null,
                'mitigation' => $f->preventive_rule ?? $f->fix,
                'report_count' => $f->report_count,
            ])
            ->values()
            ->all();

        // Active prevention rules
        $rules = $preventionRules
            ->take(10)
            ->map(fn (PreventionRule $r): array => [
                'rule_text' => $r->rule_text,
                'confidence' => (float) $r->confidence,
                'applied_count' => $r->applied_count,
            ])
            ->values()
            ->all();

        // Recent outcomes for similar entities
        $outcomes = $recentOutcomes
            ->take(5)
            ->map(fn (GenerationTrace $t): array => [
                'entity_name' => $t->entity_name,
                'structural_score' => $t->structural_score,
                'semantic_score' => $t->semantic_score,
                'fix_iterations' => $t->fix_iterations,
                'created_at' => $t->created_at?->toDateString(),
            ])
            ->values()
            ->all();

        return [
            'high_risk' => $highRisk,
            'prevention_rules' => $rules,
            'recent_outcomes' => $outcomes,
        ];
    }

    /**
     * Get recent generation traces for entities with similar context.
     *
     * @param  array<string, mixed>|null  $entityContext
     * @return Collection<int, GenerationTrace>
     */
    private function getRecentOutcomes(?array $entityContext): Collection
    {
        return GenerationTrace::query()
            ->latest()
            ->limit(5)
            ->get();
    }

    // ─── Index & Type Mapping ───────────────────────────────────

    /**
     * @return array<int, string>
     */
    private function resolveSearchTypes(?string $type): array
    {
        if ($type === null || $type === 'all') {
            return ['failure', 'lesson', 'pattern', 'prevention_rule', 'golden_annotation'];
        }

        return [$type];
    }

    private function getIndexNameForType(string $type): ?string
    {
        return match ($type) {
            'failure' => 'aicl_rlm_failures',
            'lesson' => 'aicl_rlm_lessons',
            'pattern' => 'aicl_rlm_patterns',
            'prevention_rule' => 'aicl_prevention_rules',
            'golden_annotation' => 'aicl_golden_annotations',
            default => null,
        };
    }

    /**
     * @return class-string<Model>|null
     */
    private function getModelClassForType(string $type): ?string
    {
        return match ($type) {
            'failure' => RlmFailure::class,
            'lesson' => RlmLesson::class,
            'pattern' => RlmPattern::class,
            'prevention_rule' => PreventionRule::class,
            'golden_annotation' => GoldenAnnotation::class,
            default => null,
        };
    }

    /**
     * Get the text fields to search via BM25 for a given index.
     *
     * @return array<int, string>
     */
    private function getTextFieldsForIndex(string $index): array
    {
        return match ($index) {
            'aicl_rlm_failures' => ['title^2', 'description', 'root_cause', 'fix', 'failure_code', 'preventive_rule'],
            'aicl_rlm_lessons' => ['summary^2', 'detail', 'topic', 'tags'],
            'aicl_rlm_patterns' => ['name^2', 'description', 'target', 'category'],
            'aicl_prevention_rules' => ['rule_text^2'],
            'aicl_golden_annotations' => ['annotation_text^2', 'rationale', 'annotation_key', 'pattern_name'],
            default => [],
        };
    }

    // ─── Agent/Phase Topic Mapping ──────────────────────────────

    /**
     * Map agent + phase to relevant lesson topics.
     *
     * @return array<int, string>
     */
    private function getTopicsForAgentPhase(string $agent, int $phase): array
    {
        $baseTopics = match ($agent) {
            'architect' => ['scaffolder', 'filament', 'laravel', 'testing', 'octane'],
            'solutions' => ['architecture', 'design', 'patterns'],
            'designer' => ['filament', 'tailwind', 'components', 'theming'],
            'tester' => ['testing', 'phpunit', 'dusk', 'factories'],
            'rlm' => ['validation', 'patterns', 'scoring'],
            'pm' => ['pipeline', 'process', 'coordination'],
            'docs' => ['documentation', 'architecture'],
            default => ['general'],
        };

        $phaseTopics = match ($phase) {
            1 => ['planning', 'classification'],
            2 => ['design', 'architecture', 'relationships'],
            3 => ['scaffolder', 'generation', 'filament', 'models', 'migrations'],
            4 => ['validation', 'patterns', 'testing'],
            5 => ['registration', 'routes', 'policies', 'observers'],
            6 => ['validation', 'testing'],
            7 => ['testing', 'regression', 'integration'],
            8 => ['documentation', 'changelog', 'review'],
            default => [],
        };

        return array_values(array_unique(array_merge($baseTopics, $phaseTopics)));
    }

    /**
     * Build a context string for ES queries from agent, phase, and entity features.
     *
     * @param  array<string, mixed>|null  $entityContext
     */
    private function buildContextString(string $agent, int $phase, ?array $entityContext): string
    {
        $phaseNames = [
            1 => 'plan',
            2 => 'design',
            3 => 'generate',
            4 => 'validate',
            5 => 'register',
            6 => 're-validate',
            7 => 'verify',
            8 => 'complete',
        ];

        $parts = [
            $agent,
            'working on phase',
            $phase,
            '('.($phaseNames[$phase] ?? 'unknown').')',
        ];

        if ($entityContext !== null && $entityContext !== []) {
            $features = [];
            foreach ($entityContext as $key => $value) {
                if ($value) {
                    $features[] = str_replace(['has_', '_'], ['', ' '], $key);
                }
            }
            if ($features !== []) {
                $parts[] = 'for entity with';
                $parts[] = implode(', ', $features);
            }
        }

        return implode(' ', $parts);
    }

    // ─── Utilities ──────────────────────────────────────────────

    private function getEsBaseUrl(): string
    {
        $scheme = config('aicl.search.elasticsearch.scheme', 'http');
        $host = config('aicl.search.elasticsearch.host', 'elasticsearch');
        $port = config('aicl.search.elasticsearch.port', 9200);

        return "{$scheme}://{$host}:{$port}";
    }

    private function getDefaultOwnerId(): int
    {
        return 1;
    }
}
