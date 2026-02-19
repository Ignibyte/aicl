<?php

namespace Aicl\Rlm;

use Aicl\Components\ComponentRecommendation;
use Aicl\Components\ComponentRegistry;
use Aicl\Models\GenerationTrace;
use Aicl\Models\GoldenAnnotation;
use Aicl\Models\PreventionRule;
use Aicl\Models\RlmFailure;
use Aicl\Models\RlmLesson;
use Aicl\Models\RlmScore;
use Illuminate\Support\Collection;

/**
 * Agent-facing recall orchestration for the RLM knowledge system.
 *
 * Combines ES hybrid search with deterministic topic lookups,
 * prevention rules, recent outcomes, and golden annotations
 * to build a complete context for agent phases.
 */
class RecallService
{
    public function __construct(
        private KnowledgeSearchEngine $searchEngine,
        private EmbeddingService $embeddingService,
    ) {}

    /**
     * Agent-facing context retrieval with risk briefing.
     *
     * @param  array<string, mixed>|null  $entityContext
     * @return array{failures: Collection, lessons: Collection, scores: Collection, prevention_rules: Collection, golden_annotations: Collection, risk_briefing: array, component_recommendations: array}
     */
    public function recall(
        string $agent,
        int $phase,
        ?array $entityContext = null,
        ?string $entityName = null,
        bool $esAvailable = false,
    ): array {
        $contextString = $this->buildContextString($agent, $phase, $entityContext);
        $topicMap = $this->getTopicsForAgentPhase($agent, $phase);

        // 1. Search for failures via ES or deterministic fallback
        $failures = $this->searchFailuresForRecall($contextString, $entityContext, $esAvailable);

        // 2. Deterministic topic-based lessons + search-based lessons
        $lessons = $this->searchLessonsForRecall($contextString, $topicMap, $esAvailable);

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
        $goldenAnnotations = $this->getGoldenAnnotationsForRecall($contextString, $entityContext, $esAvailable);

        // 6. Recent outcomes for similar entities
        $recentOutcomes = $this->getRecentOutcomes($entityContext);

        // 7. Build risk briefing
        $riskBriefing = $this->buildRiskBriefing($failures, $preventionRules, $recentOutcomes, $entityContext);

        // 8. Component recommendations (for architect/designer on generate/style phases)
        $componentRecommendations = $this->getComponentRecommendations($agent, $phase, $entityContext);

        return [
            'failures' => $failures,
            'lessons' => $lessons,
            'scores' => $scores,
            'prevention_rules' => $preventionRules,
            'golden_annotations' => $goldenAnnotations,
            'risk_briefing' => $riskBriefing,
            'component_recommendations' => $componentRecommendations,
        ];
    }

    // ─── Recall Helpers ─────────────────────────────────────────

    /**
     * @param  array<string, mixed>|null  $entityContext
     * @return Collection<int, RlmFailure>
     */
    public function searchFailuresForRecall(string $contextString, ?array $entityContext, bool $esAvailable): Collection
    {
        // Try ES search first
        if ($esAvailable) {
            $embedding = $this->embeddingService->isAvailable()
                ? $this->embeddingService->generate($contextString)
                : null;

            $esResults = $this->searchEngine->executeEsSearch('aicl_rlm_failures', $contextString, $embedding, 20);

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
    public function searchLessonsForRecall(string $contextString, array $topicMap, bool $esAvailable): Collection
    {
        // Deterministic: topic-based lessons — only surfaceable + verified
        $topicLessons = collect();
        foreach ($topicMap as $topic) {
            $topicLessons = $topicLessons->merge(
                RlmLesson::query()
                    ->byTopic($topic)
                    ->where('is_active', true)
                    ->surfaceable()
                    ->verified()
                    ->get(),
            );
        }

        // ES search for broader matches
        if ($esAvailable) {
            $embedding = $this->embeddingService->isAvailable()
                ? $this->embeddingService->generate($contextString)
                : null;

            $esResults = $this->searchEngine->executeEsSearch('aicl_rlm_lessons', $contextString, $embedding, 15);

            if ($esResults !== null) {
                $ids = collect($esResults)->pluck('_id')->all();

                $esLessons = $ids !== []
                    ? RlmLesson::query()
                        ->whereIn('id', $ids)
                        ->surfaceable()
                        ->verified()
                        ->get()
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
    public function getPreventionRulesForContext(?array $entityContext): Collection
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
    public function getGoldenAnnotationsForRecall(string $contextString, ?array $entityContext, bool $esAvailable): Collection
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
        if ($esAvailable) {
            $embedding = $this->embeddingService->isAvailable()
                ? $this->embeddingService->generate($contextString)
                : null;

            $esResults = $this->searchEngine->executeEsSearch('aicl_golden_annotations', $contextString, $embedding, 20);

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
    public function extractFeatureTags(?array $entityContext): array
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
    public function buildRiskBriefing(
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
                'severity' => $f->severity?->value, // @phpstan-ignore nullsafe.neverNull
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
    public function getRecentOutcomes(?array $entityContext): Collection
    {
        return GenerationTrace::query()
            ->latest()
            ->limit(5)
            ->get();
    }

    // ─── Agent/Phase Topic Mapping ──────────────────────────────

    /**
     * Map agent + phase to relevant lesson topics.
     *
     * @return array<int, string>
     */
    public function getTopicsForAgentPhase(string $agent, int $phase): array
    {
        $baseTopics = match ($agent) {
            'architect' => ['scaffolder', 'filament', 'laravel', 'testing', 'octane', 'components'],
            'solutions' => ['architecture', 'design', 'patterns'],
            'designer' => ['filament', 'tailwind', 'components', 'theming'],
            'tester' => ['testing', 'phpunit', 'dusk', 'factories'],
            'rlm' => ['validation', 'patterns', 'scoring', 'components'],
            'pm' => ['pipeline', 'process', 'coordination'],
            'docs' => ['documentation', 'architecture'],
            default => ['general'],
        };

        $phaseTopics = match ($phase) {
            1 => ['planning', 'classification'],
            2 => ['design', 'architecture', 'relationships'],
            3 => ['scaffolder', 'generation', 'filament', 'models', 'migrations', 'components'],
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
    public function buildContextString(string $agent, int $phase, ?array $entityContext): string
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

    // ─── Component Recommendations ────────────────────────────

    /**
     * Get component recommendations for entity fields via ComponentRegistry.
     *
     * Only returns recommendations for agents/phases that deal with UI generation.
     *
     * @param  array<string, mixed>|null  $entityContext
     * @return array<string, mixed>
     */
    public function getComponentRecommendations(string $agent, int $phase, ?array $entityContext): array
    {
        // Only relevant for architect (generate/register) and designer (style)
        if (! in_array($agent, ['architect', 'designer', 'rlm']) || ! in_array($phase, [3, 4, 5, 6])) {
            return [];
        }

        // Need entity fields to make recommendations
        $fields = $entityContext['fields'] ?? [];
        if ($fields === []) {
            return [];
        }

        try {
            $registry = app(ComponentRegistry::class);
        } catch (\Throwable) {
            return [];
        }

        $recommendations = $registry->recommendForEntity($fields, 'blade', 'index');
        if ($recommendations === []) {
            return [];
        }

        return [
            'context_rules' => [
                'blade' => 'Use <x-aicl-*> components',
                'filament-form' => 'Use Filament form components, NOT <x-aicl-*>',
                'filament-table' => 'Use Filament table columns, NOT <x-aicl-*>',
                'filament-widget' => 'Can use <x-aicl-*> in widget Blade views',
            ],
            'field_recommendations' => array_map(
                fn (ComponentRecommendation $r): array => [
                    'component' => $r->tag,
                    'reason' => $r->reason,
                    'confidence' => $r->confidence,
                    'suggested_props' => $r->suggestedProps,
                    'filament_alternative' => $r->alternative,
                ],
                $recommendations,
            ),
            'categories' => $registry->categories(),
            'total_components' => $registry->count(),
        ];
    }

    // ─── Private Helpers ────────────────────────────────────────

    /**
     * Get failures matching an entity context via JSONB queries.
     *
     * @param  array<string, mixed>  $context
     * @return Collection<int, RlmFailure>
     */
    private function getFailuresByContext(array $context): Collection
    {
        $query = RlmFailure::query()->where('is_active', true);

        if ($context !== []) {
            $query->where(function ($q) use ($context) {
                $q->whereNull('entity_context');
                foreach ($context as $key => $value) {
                    $q->orWhereJsonContains('entity_context->'.$key, $value);
                }
            });
        }

        return $query->orderByRaw("CASE severity
            WHEN 'critical' THEN 1
            WHEN 'high' THEN 2
            WHEN 'medium' THEN 3
            WHEN 'low' THEN 4
            ELSE 5
        END")->get();
    }
}
