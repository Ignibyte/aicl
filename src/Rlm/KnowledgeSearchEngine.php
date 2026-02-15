<?php

namespace Aicl\Rlm;

use Aicl\Models\DistilledLesson;
use Aicl\Models\GoldenAnnotation;
use Aicl\Models\PreventionRule;
use Aicl\Models\RlmFailure;
use Aicl\Models\RlmLesson;
use Aicl\Models\RlmPattern;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Elasticsearch hybrid search engine for the RLM knowledge system.
 *
 * Handles ES hybrid search (kNN + BM25 + RRF), deterministic fallback,
 * type/index mapping, and query building.
 */
class KnowledgeSearchEngine
{
    public function __construct(
        private EmbeddingService $embeddingService,
    ) {}

    /**
     * Search across knowledge types using ES hybrid or deterministic fallback.
     *
     * @param  array<int, string>|null  $types  Resolved type keys, or null for all
     * @return Collection<int, Model>
     */
    public function search(string $query, ?string $type, int $limit, bool $esAvailable): Collection
    {
        $types = $this->resolveSearchTypes($type);

        if ($esAvailable) {
            return $this->searchViaElasticsearch($query, $types, $limit);
        }

        return $this->searchViaDeterministic($query, $types, $limit);
    }

    // ─── Elasticsearch Search ───────────────────────────────────

    /**
     * @param  array<int, string>  $types
     * @return Collection<int, Model>
     */
    public function searchViaElasticsearch(string $query, array $types, int $limit): Collection
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
    public function executeEsSearch(string $index, string $query, ?array $embedding, int $limit): ?array
    {
        $body = $this->buildEsSearchBody($index, $query, $embedding, $limit);

        try {
            $response = Http::timeout(5)
                ->post("{$this->getEsBaseUrl()}/{$index}/_search", $body);

            if (! $response->successful()) {
                Log::warning("KnowledgeSearchEngine: ES search failed for {$index}", [
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
            Log::warning('KnowledgeSearchEngine: ES search exception', [
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
    public function buildEsSearchBody(string $index, string $query, ?array $embedding, int $limit): array
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
    public function searchViaDeterministic(string $query, array $types, int $limit): Collection
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
    public function deterministicSearchForType(string $query, string $type, int $limit): Collection
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
            'distilled_lesson' => DistilledLesson::query()
                ->where('is_active', true)
                ->where(function ($q) use ($likeQuery) {
                    $q->whereRaw('LOWER(title) LIKE ?', [$likeQuery])
                        ->orWhereRaw('LOWER(guidance) LIKE ?', [$likeQuery])
                        ->orWhereRaw('LOWER(lesson_code) LIKE ?', [$likeQuery])
                        ->orWhereRaw('LOWER(target_agent) LIKE ?', [$likeQuery]);
                })
                ->limit($limit)
                ->get(),
            default => collect(),
        };
    }

    // ─── Index & Type Mapping ───────────────────────────────────

    /**
     * @return array<int, string>
     */
    public function resolveSearchTypes(?string $type): array
    {
        if ($type === null || $type === 'all') {
            return ['failure', 'lesson', 'pattern', 'prevention_rule', 'golden_annotation', 'distilled_lesson'];
        }

        return [$type];
    }

    public function getIndexNameForType(string $type): ?string
    {
        return match ($type) {
            'failure' => 'aicl_rlm_failures',
            'lesson' => 'aicl_rlm_lessons',
            'pattern' => 'aicl_rlm_patterns',
            'prevention_rule' => 'aicl_prevention_rules',
            'golden_annotation' => 'aicl_golden_annotations',
            'distilled_lesson' => 'aicl_distilled_lessons',
            default => null,
        };
    }

    /**
     * @return class-string<Model>|null
     */
    public function getModelClassForType(string $type): ?string
    {
        return match ($type) {
            'failure' => RlmFailure::class,
            'lesson' => RlmLesson::class,
            'pattern' => RlmPattern::class,
            'prevention_rule' => PreventionRule::class,
            'golden_annotation' => GoldenAnnotation::class,
            'distilled_lesson' => DistilledLesson::class,
            default => null,
        };
    }

    /**
     * Get the text fields to search via BM25 for a given index.
     *
     * @return array<int, string>
     */
    public function getTextFieldsForIndex(string $index): array
    {
        return match ($index) {
            'aicl_rlm_failures' => ['title^2', 'description', 'root_cause', 'fix', 'failure_code', 'preventive_rule'],
            'aicl_rlm_lessons' => ['summary^2', 'detail', 'topic', 'tags'],
            'aicl_rlm_patterns' => ['name^2', 'description', 'target', 'category'],
            'aicl_prevention_rules' => ['rule_text^2'],
            'aicl_golden_annotations' => ['annotation_text^2', 'rationale', 'annotation_key', 'pattern_name'],
            'aicl_distilled_lessons' => ['title^2', 'guidance', 'lesson_code', 'target_agent'],
            default => [],
        };
    }

    // ─── Utilities ──────────────────────────────────────────────

    public function getEsBaseUrl(): string
    {
        $scheme = config('aicl.search.elasticsearch.scheme', 'http');
        $host = config('aicl.search.elasticsearch.host', 'elasticsearch');
        $port = config('aicl.search.elasticsearch.port', 9200);

        return "{$scheme}://{$host}:{$port}";
    }
}
