<?php

namespace Aicl\Rlm;

use Aicl\Enums\FailureCategory;
use Aicl\Enums\FailureSeverity;
use Aicl\Enums\LessonType;
use Aicl\Models\DistilledLesson;
use Aicl\Models\KnowledgeLink;
use Aicl\Models\RlmFailure;
use Aicl\Models\RlmLesson;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DistillationService
{
    /**
     * Cluster related failures that share the same root cause.
     *
     * Algorithm (deterministic, no LLM):
     * 1. Load all active base failures (BF-*)
     * 2. Group by rule_hash (deterministic — same normalized rule = same cluster)
     * 3. Group remaining by exact pattern_id match
     * 4. Group remaining by category + subcategory with root_cause similarity (legacy fallback)
     * 5. Any remaining unclustered failures become single-failure clusters
     *
     * @return Collection<int, array{canonical: RlmFailure, failures: Collection<int, RlmFailure>}>
     */
    public function clusterFailures(): Collection
    {
        $failures = RlmFailure::query()
            ->where('is_active', true)
            ->whereRaw("failure_code LIKE 'BF-%'")
            ->orderBy('failure_code')
            ->get();

        $clusters = collect();
        $clustered = collect();

        // Pass 1: Group by rule_hash (deterministic — Phase B.1)
        // This is the primary clustering mechanism. Failures with the same normalized
        // preventive_rule text produce the same rule_hash and cluster together,
        // regardless of minor wording variations.
        $withRuleHash = $failures->filter(fn (RlmFailure $f) => $f->rule_hash !== null);
        $hashGroups = $withRuleHash->groupBy('rule_hash');

        foreach ($hashGroups as $group) {
            $canonical = $this->selectCanonical($group);
            $clusters->push([
                'canonical' => $canonical,
                'failures' => $group,
            ]);
            $clustered = $clustered->merge($group->pluck('id'));
        }

        // Pass 2: Group remaining by exact pattern_id (legacy records without rule_hash)
        $remaining = $failures->reject(fn (RlmFailure $f) => $clustered->contains($f->id));
        $withPatternId = $remaining->filter(fn (RlmFailure $f) => $f->pattern_id !== null);
        $patternGroups = $withPatternId->groupBy('pattern_id');

        foreach ($patternGroups as $group) {
            if ($group->count() > 1) {
                $canonical = $this->selectCanonical($group);
                $clusters->push([
                    'canonical' => $canonical,
                    'failures' => $group,
                ]);
                $clustered = $clustered->merge($group->pluck('id'));
            }
        }

        // Pass 3: Group remaining by category + subcategory (legacy fuzzy fallback)
        $remaining = $failures->reject(fn (RlmFailure $f) => $clustered->contains($f->id));
        $categoryGroups = $remaining->groupBy(function (RlmFailure $f) {
            $category = $f->category->value;
            $subcategory = $f->subcategory ?? '_none_';

            return $category.'|'.$subcategory;
        });

        foreach ($categoryGroups as $group) {
            if ($group->count() > 1) {
                // Check root_cause similarity within category group
                $subClusters = $this->clusterByRootCause($group);
                foreach ($subClusters as $subCluster) {
                    $canonical = $this->selectCanonical($subCluster);
                    $clusters->push([
                        'canonical' => $canonical,
                        'failures' => $subCluster,
                    ]);
                    $clustered = $clustered->merge($subCluster->pluck('id'));
                }
            }
        }

        // Pass 4: Any unclustered failures become single-failure clusters
        $unclustered = $failures->reject(fn (RlmFailure $f) => $clustered->contains($f->id));
        foreach ($unclustered as $failure) {
            $clusters->push([
                'canonical' => $failure,
                'failures' => collect([$failure]),
            ]);
        }

        return $clusters->values();
    }

    /**
     * Run the full distillation pipeline.
     *
     * @return array{clusters: int, lessons: int, agents: array<string, int>}
     */
    public function distill(?string $agentFilter = null): array
    {
        Log::info('DistillationService: distill() started', [
            'agent_filter' => $agentFilter,
        ]);

        $clusters = $this->clusterFailures();
        $perspectives = $this->getAgentPerspectives();

        if ($agentFilter !== null) {
            $perspectives = collect($perspectives)
                ->filter(fn (array $p, string $agent) => $agent === $agentFilter)
                ->all();
        }

        $ownerId = auth()->id() ?? (int) config('aicl.default_owner_id', 1);
        $lessonCount = 0;
        $agentCounts = [];

        DB::transaction(function () use ($clusters, $perspectives, $ownerId, &$lessonCount, &$agentCounts) {
            foreach ($clusters as $cluster) {
                /** @var RlmFailure $canonical */
                $canonical = $cluster['canonical'];

                /** @var Collection<int, RlmFailure> $failures */
                $failures = $cluster['failures'];

                $failureCodes = $failures->pluck('failure_code')->sort()->values()->all();
                $impactScore = $this->computeImpactScore($failures);

                $this->upsertLessonsForCluster(
                    $canonical, $failureCodes, $impactScore, $perspectives, $ownerId, $lessonCount, $agentCounts
                );
            }
        });

        Log::info('DistillationService: distill() completed', [
            'clusters' => $clusters->count(),
            'lessons' => $lessonCount,
            'agents' => $agentCounts,
        ]);

        return [
            'clusters' => $clusters->count(),
            'lessons' => $lessonCount,
            'agents' => $agentCounts,
        ];
    }

    /**
     * Get agent-specific perspectives for distillation.
     *
     * @return array<string, array{phases: array<int, int>, prompt_template: string, categories: array<int, FailureCategory>}>
     */
    public function getAgentPerspectives(): array
    {
        return [
            'architect' => [
                'phases' => [3, 5],
                'prompt_template' => 'When generating {component}: {preventive_rule}',
                'categories' => [FailureCategory::Scaffolding, FailureCategory::Filament, FailureCategory::Laravel],
            ],
            'tester' => [
                'phases' => [4, 6, 7],
                'prompt_template' => 'When testing {component}: verify that {test_assertion}',
                'categories' => [FailureCategory::Testing, FailureCategory::Scaffolding],
            ],
            'rlm' => [
                'phases' => [4, 6],
                'prompt_template' => 'Pattern validation should check: {validation_check}',
                'categories' => [FailureCategory::Scaffolding, FailureCategory::Filament],
            ],
            'designer' => [
                'phases' => [3],
                'prompt_template' => 'When reviewing UI: {design_check}',
                'categories' => [FailureCategory::Filament, FailureCategory::Tailwind],
            ],
            'solutions' => [
                'phases' => [2],
                'prompt_template' => 'When designing entities: account for {architectural_constraint}',
                'categories' => [FailureCategory::Process, FailureCategory::Configuration],
            ],
            'pm' => [
                'phases' => [1, 7, 8],
                'prompt_template' => 'When managing pipeline: ensure {process_rule}',
                'categories' => [FailureCategory::Process, FailureCategory::Configuration],
            ],
        ];
    }

    /**
     * Compute impact score for a cluster of failures.
     *
     * @param  Collection<int, RlmFailure>  $failures
     */
    public function computeImpactScore(Collection $failures): float
    {
        return $failures->sum(function (RlmFailure $f) {
            $severityWeight = $this->getSeverityWeight($f->severity);
            $reportCount = max(1, $f->report_count ?? 1);
            $scaffoldingFactor = $f->scaffolding_fixed ? 0.3 : 1.0;

            return $reportCount * $severityWeight * $scaffoldingFactor;
        });
    }

    /**
     * Get the numeric weight for a failure severity.
     */
    public function getSeverityWeight(FailureSeverity|string $severity): int
    {
        if (is_string($severity)) {
            $severity = FailureSeverity::tryFrom($severity) ?? FailureSeverity::Low;
        }

        return match ($severity) {
            FailureSeverity::Critical => 10,
            FailureSeverity::High => 5,
            FailureSeverity::Medium => 2,
            FailureSeverity::Low => 1,
            FailureSeverity::Informational => 0,
        };
    }

    /**
     * Get the top distilled lessons for an agent/phase combo.
     *
     * @return Collection<int, DistilledLesson>
     */
    public function getTopLessons(string $agent, int $phase, int $limit = 5, ?array $entityContext = null): Collection
    {
        $query = DistilledLesson::query()
            ->where('target_agent', $agent)
            ->where('target_phase', $phase)
            ->where('is_active', true)
            ->orderByRaw('impact_score * GREATEST(confidence, 0.1) DESC');

        if ($entityContext !== null) {
            $activeFeatures = array_keys(array_filter($entityContext));
            foreach ($activeFeatures as $feature) {
                $query->orWhere(function ($q) use ($agent, $phase, $feature) {
                    $q->where('target_agent', $agent)
                        ->where('target_phase', $phase)
                        ->where('is_active', true)
                        ->whereJsonContains('trigger_context', [$feature => true])
                        ->orderByRaw('impact_score * GREATEST(confidence, 0.1) DESC');
                });
            }
        }

        $lessons = $query->limit($limit)->get();

        // Bulk increment surfaced_count for recalled lessons (single query)
        if ($lessons->isNotEmpty()) {
            DistilledLesson::query()
                ->whereIn('id', $lessons->pluck('id'))
                ->increment('surfaced_count');
        }

        return $lessons;
    }

    /**
     * Generate When-Then rules from distilled lessons for an agent/phase.
     *
     * Phase B.2: When lessons have structured guidance with WHEN/THEN format,
     * extract those directly. Falls back to trigger_context-based grouping
     * for lessons without structured guidance.
     *
     * @return Collection<int, array{when: string, then: array<mixed>}>
     */
    public function generateWhenThenRules(string $agent, int $phase): Collection
    {
        $lessons = DistilledLesson::query()
            ->where('target_agent', $agent)
            ->where('target_phase', $phase)
            ->where('is_active', true)
            ->orderByRaw('impact_score * GREATEST(confidence, 0.1) DESC')
            ->get();

        // Phase B.2: Split lessons into structured (WHEN/THEN guidance) and legacy
        $structured = $lessons->filter(fn (DistilledLesson $l) => Str::startsWith($l->guidance ?? '', 'WHEN:'));
        $legacy = $lessons->reject(fn (DistilledLesson $l) => Str::startsWith($l->guidance ?? '', 'WHEN:'))
            ->filter(fn (DistilledLesson $l) => $l->trigger_context !== null);

        $rules = collect();

        // Extract WHEN/THEN from structured guidance text
        foreach ($structured as $lesson) {
            $parsed = $this->parseStructuredGuidance($lesson->guidance);
            if ($parsed !== null) {
                $rules->push($parsed);
            }
        }

        // Legacy: group by trigger_context
        $legacyRules = $legacy
            ->groupBy(fn (DistilledLesson $lesson) => json_encode($lesson->trigger_context))
            ->map(function (Collection $group) {
                $context = $group->first()->trigger_context;
                $conditions = collect($context)
                    ->map(fn ($value, $key) => is_bool($value) && $value ? $key : "{$key}={$value}")
                    ->implode(', ');

                return [
                    'when' => $conditions,
                    'then' => $group->pluck('title')->all(),
                ];
            })
            ->values();

        return $rules->merge($legacyRules)->values();
    }

    /**
     * Parse structured WHEN/THEN/RULE guidance into a when-then rule array.
     *
     * @return array{when: string, then: array<string>}|null
     */
    private function parseStructuredGuidance(string $guidance): ?array
    {
        $when = null;
        $then = [];

        foreach (explode("\n", $guidance) as $line) {
            $line = trim($line);
            if (Str::startsWith($line, 'WHEN:')) {
                $when = trim(Str::after($line, 'WHEN:'));
            } elseif (Str::startsWith($line, 'THEN:')) {
                $then[] = trim(Str::after($line, 'THEN:'));
            } elseif (Str::startsWith($line, 'RULE:')) {
                $then[] = trim(Str::after($line, 'RULE:'));
            } elseif (Str::startsWith($line, 'FIX:')) {
                $then[] = trim(Str::after($line, 'FIX:'));
            }
        }

        if ($when === null || empty($then)) {
            return null;
        }

        return [
            'when' => $when,
            'then' => $then,
        ];
    }

    /**
     * Get distillation coverage stats.
     *
     * @return array{total_failures: int, clustered_failures: int, total_clusters: int, total_lessons: int, agents: array<string, int>}
     */
    public function getStats(): array
    {
        $clusters = $this->clusterFailures();
        $perspectives = $this->getAgentPerspectives();

        $totalFailures = $clusters->sum(fn (array $c) => $c['failures']->count());
        $agentCounts = [];

        foreach ($clusters as $cluster) {
            $category = $cluster['canonical']->category;

            foreach ($perspectives as $agent => $perspective) {
                if ($this->isRelevantToAgent($category, $perspective['categories'])) {
                    $agentCounts[$agent] = ($agentCounts[$agent] ?? 0) + count($perspective['phases']);
                }
            }
        }

        return [
            'total_failures' => $totalFailures,
            'clustered_failures' => $clusters->filter(fn (array $c) => $c['failures']->count() > 1)->sum(fn (array $c) => $c['failures']->count()),
            'total_clusters' => $clusters->count(),
            'total_lessons' => array_sum($agentCounts),
            'agents' => $agentCounts,
        ];
    }

    /**
     * Recalculate confidence for a distilled lesson based on prevention/ignore counts.
     *
     * +2% per prevention, -5% per ignore, clamped to [0.0, 1.0].
     */
    public function recalculateConfidence(DistilledLesson $lesson): float
    {
        $confidence = min(1.0, max(0.0,
            (float) $lesson->confidence
            + ($lesson->prevented_count * 0.02)
            - ($lesson->ignored_count * 0.05)
        ));

        $lesson->update(['confidence' => $confidence]);

        return $confidence;
    }

    /**
     * Auto-deactivate distilled lessons with confidence below threshold.
     *
     * @return int Number of lessons deactivated
     */
    public function autoDeactivateLowConfidence(float $threshold = 0.2): int
    {
        $deactivated = DistilledLesson::query()
            ->where('is_active', true)
            ->where('confidence', '<', $threshold)
            ->update(['is_active' => false]);

        if ($deactivated > 0) {
            Log::info('DistillationService: auto-deactivated low-confidence lessons', [
                'count' => $deactivated,
                'threshold' => $threshold,
            ]);
        }

        // Also deactivate lessons surfaced 50+ times with zero interactions
        $staleBySurfacing = DistilledLesson::query()
            ->where('is_active', true)
            ->where('surfaced_count', '>=', 50)
            ->where('prevented_count', 0)
            ->where('ignored_count', 0)
            ->update(['is_active' => false]);

        if ($staleBySurfacing > 0) {
            Log::info('DistillationService: auto-deactivated stale-by-surfacing lessons', [
                'count' => $staleBySurfacing,
            ]);
        }

        return $deactivated + $staleBySurfacing;
    }

    /**
     * Flag stale RlmLesson records that have had no positive signal (prevented_count=0 on
     * linked distilled lessons) within the last N generations of distillation.
     *
     * @return int Number of lessons flagged for review
     */
    public function flagStaleLessons(int $generationThreshold = 10): int
    {
        // Find RlmLessons that are active instructions/prevention_rules
        // but have never been surfaced or had positive signal
        $staleLessons = RlmLesson::query()
            ->where('is_active', true)
            ->where('needs_review', false)
            ->whereIn('lesson_type', [LessonType::Instruction->value, LessonType::PreventionRule->value])
            ->where('view_count', 0)
            ->where('created_at', '<', now()->subDays($generationThreshold * 7))
            ->get();

        $flagged = 0;
        foreach ($staleLessons as $lesson) {
            $lesson->update(['needs_review' => true]);
            $flagged++;
        }

        if ($flagged > 0) {
            Log::info('DistillationService: flagged stale lessons for review', [
                'count' => $flagged,
                'generation_threshold' => $generationThreshold,
            ]);
        }

        return $flagged;
    }

    /**
     * Promote an observation to instruction when it clusters with >= $minOccurrences
     * in the failure data.
     *
     * Requires at least one KnowledgeLink OR marks needs_review.
     *
     * @return array{promoted: bool, reason: string}
     */
    public function promoteObservation(RlmLesson $lesson, int $clusterSize, int $minOccurrences = 3): array
    {
        if ($lesson->lesson_type !== LessonType::Observation) {
            return ['promoted' => false, 'reason' => 'Not an observation'];
        }

        if ($clusterSize < $minOccurrences) {
            return ['promoted' => false, 'reason' => "Cluster size {$clusterSize} below threshold {$minOccurrences}"];
        }

        // Check for proof hooks (KnowledgeLinks)
        $hasProof = KnowledgeLink::query()
            ->where('source_type', $lesson->getMorphClass())
            ->where('source_id', $lesson->getKey())
            ->exists();

        $promotionReason = "Cluster size: {$clusterSize} (threshold: {$minOccurrences})";

        if ($hasProof) {
            $lesson->update([
                'lesson_type' => LessonType::Instruction,
                'promotion_reason' => $promotionReason.'. Proof hooks: present.',
                'is_verified' => true,
            ]);

            Log::info('DistillationService: promoted observation to instruction', [
                'lesson_id' => $lesson->id,
                'cluster_size' => $clusterSize,
            ]);

            return ['promoted' => true, 'reason' => 'Promoted with proof hooks'];
        }

        // No proof → flag for review instead of promoting
        $lesson->update([
            'needs_review' => true,
            'promotion_reason' => $promotionReason.'. Proof hooks: missing — flagged for review.',
        ]);

        Log::info('DistillationService: observation promotion blocked — no proof hooks', [
            'lesson_id' => $lesson->id,
            'cluster_size' => $clusterSize,
        ]);

        return ['promoted' => false, 'reason' => 'Missing proof hooks — flagged for review'];
    }

    /**
     * Re-distill a specific cluster of failures by their failure codes.
     *
     * Loads the specified failures, runs them through clustering + lesson generation,
     * and upserts affected DistilledLesson records with an incremented generation counter.
     *
     * @param  array<int, string>  $failureCodes
     * @return array{clusters: int, lessons: int, agents: array<string, int>}
     */
    public function distillCluster(array $failureCodes): array
    {
        Log::info('DistillationService: distillCluster() started', [
            'failure_codes' => $failureCodes,
        ]);

        $failures = RlmFailure::query()
            ->whereIn('failure_code', $failureCodes)
            ->where('is_active', true)
            ->orderBy('failure_code')
            ->get();

        if ($failures->isEmpty()) {
            return ['clusters' => 0, 'lessons' => 0, 'agents' => []];
        }

        $perspectives = $this->getAgentPerspectives();
        $ownerId = auth()->id() ?? (int) config('aicl.default_owner_id', 1);
        $lessonCount = 0;
        $agentCounts = [];

        DB::transaction(function () use ($failures, $perspectives, $ownerId, &$lessonCount, &$agentCounts) {
            // Treat the provided failures as a single cluster
            $canonical = $this->selectCanonical($failures);
            $allCodes = $failures->pluck('failure_code')->sort()->values()->all();
            $impactScore = $this->computeImpactScore($failures);

            $this->upsertLessonsForCluster(
                $canonical, $allCodes, $impactScore, $perspectives, $ownerId, $lessonCount, $agentCounts
            );
        });

        Log::info('DistillationService: distillCluster() completed', [
            'failure_codes' => $failureCodes,
            'lessons' => $lessonCount,
            'agents' => $agentCounts,
        ]);

        return [
            'clusters' => 1,
            'lessons' => $lessonCount,
            'agents' => $agentCounts,
        ];
    }

    /**
     * Upsert distilled lessons for a single failure cluster across all relevant agent perspectives.
     *
     * @param  array<int, string>  $failureCodes
     * @param  array<string, array{phases: array<int, int>, prompt_template: string, categories: array<int, FailureCategory>}>  $perspectives
     * @param  array<string, int>  $agentCounts
     */
    private function upsertLessonsForCluster(
        RlmFailure $canonical,
        array $failureCodes,
        float $impactScore,
        array $perspectives,
        int $ownerId,
        int &$lessonCount,
        array &$agentCounts,
    ): void {
        $category = $canonical->category;

        foreach ($perspectives as $agent => $perspective) {
            if (! $this->isRelevantToAgent($category, $perspective['categories'])) {
                continue;
            }

            foreach ($perspective['phases'] as $phase) {
                $lessonCode = $this->generateLessonCode($canonical->failure_code, $agent, $phase);
                $guidance = $this->generateGuidance($canonical, $perspective['prompt_template'], $agent);
                $title = $this->generateTitle($canonical, $agent, $phase);

                $existing = DistilledLesson::query()
                    ->where('lesson_code', $lessonCode)
                    ->first();

                $generation = $existing ? $existing->generation + 1 : 1;

                DistilledLesson::query()->updateOrCreate(
                    ['lesson_code' => $lessonCode],
                    [
                        'title' => $title,
                        'guidance' => $guidance,
                        'target_agent' => $agent,
                        'target_phase' => $phase,
                        'trigger_context' => $this->extractTriggerContext($canonical),
                        'source_failure_codes' => $failureCodes,
                        'source_lesson_ids' => null,
                        'impact_score' => $impactScore,
                        'confidence' => $existing ? $existing->confidence : 0.8,
                        'is_active' => true,
                        'last_distilled_at' => now(),
                        'generation' => $generation,
                        'owner_id' => $ownerId,
                    ]
                );

                $lessonCount++;
                $agentCounts[$agent] = ($agentCounts[$agent] ?? 0) + 1;
            }
        }
    }

    /**
     * Select the canonical failure from a cluster.
     * Prefers highest severity, then highest report_count.
     *
     * @param  Collection<int, RlmFailure>  $failures
     */
    private function selectCanonical(Collection $failures): RlmFailure
    {
        return $failures->sortBy([
            fn (RlmFailure $a, RlmFailure $b) => $this->getSeverityWeight($b->severity) <=> $this->getSeverityWeight($a->severity),
            fn (RlmFailure $a, RlmFailure $b) => ($b->report_count ?? 0) <=> ($a->report_count ?? 0),
        ])->first();
    }

    /**
     * Sub-cluster failures within a category group by root_cause similarity.
     *
     * Uses a simple word-overlap heuristic: if two failures share >= 50% of
     * significant words in their root_cause, they cluster together.
     *
     * @param  Collection<int, RlmFailure>  $failures
     * @return Collection<int, Collection<int, RlmFailure>>
     */
    private function clusterByRootCause(Collection $failures): Collection
    {
        $assigned = collect();
        $clusters = collect();

        foreach ($failures as $failure) {
            if ($assigned->contains($failure->id)) {
                continue;
            }

            $cluster = collect([$failure]);
            $assigned->push($failure->id);

            $words = $this->extractSignificantWords($failure->root_cause ?? '');

            foreach ($failures as $candidate) {
                if ($assigned->contains($candidate->id)) {
                    continue;
                }

                $candidateWords = $this->extractSignificantWords($candidate->root_cause ?? '');
                $overlap = $this->wordOverlap($words, $candidateWords);

                if ($overlap >= 0.5) {
                    $cluster->push($candidate);
                    $assigned->push($candidate->id);
                }
            }

            $clusters->push($cluster);
        }

        return $clusters;
    }

    /**
     * Extract significant words from text (removing stop words, normalizing).
     *
     * @return array<int, string>
     */
    private function extractSignificantWords(string $text): array
    {
        $stopWords = ['the', 'a', 'an', 'is', 'are', 'was', 'were', 'be', 'been', 'being',
            'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would', 'could', 'should',
            'may', 'might', 'can', 'shall', 'to', 'of', 'in', 'for', 'on', 'with', 'at',
            'by', 'from', 'as', 'into', 'through', 'during', 'before', 'after', 'above',
            'below', 'between', 'out', 'off', 'over', 'under', 'again', 'further', 'then',
            'once', 'and', 'but', 'or', 'nor', 'not', 'no', 'so', 'than', 'too', 'very',
            'just', 'about', 'up', 'down', 'each', 'every', 'all', 'both', 'few', 'more',
            'most', 'other', 'some', 'such', 'only', 'own', 'same', 'this', 'that', 'these',
            'those', 'it', 'its', 'he', 'she', 'they', 'them', 'their', 'what', 'which',
            'who', 'whom', 'when', 'where', 'why', 'how'];

        $words = str_word_count(mb_strtolower($text), 1);

        return array_values(array_diff($words, $stopWords));
    }

    /**
     * Compute word overlap ratio between two word arrays.
     *
     * @param  array<int, string>  $wordsA
     * @param  array<int, string>  $wordsB
     */
    private function wordOverlap(array $wordsA, array $wordsB): float
    {
        if (empty($wordsA) || empty($wordsB)) {
            return 0.0;
        }

        $intersection = count(array_intersect($wordsA, $wordsB));
        $minCount = min(count($wordsA), count($wordsB));

        return $intersection / $minCount;
    }

    /**
     * Check if a failure category is relevant to an agent perspective.
     *
     * @param  array<int, FailureCategory>  $agentCategories
     */
    private function isRelevantToAgent(FailureCategory $failureCategory, array $agentCategories): bool
    {
        return in_array($failureCategory, $agentCategories, true);
    }

    /**
     * Generate guidance text for a distilled lesson.
     *
     * When structured reflection fields are available (Phase B.2), derives
     * WHEN/THEN guidance from feedback/fix fields. Falls back to template-based
     * guidance for legacy records without structured fields.
     */
    private function generateGuidance(RlmFailure $canonical, string $template, string $agent): string
    {
        // Phase B.2: Use structured fields when available
        if ($canonical->preventive_rule !== null && ($canonical->feedback !== null || $canonical->fix !== null)) {
            return $this->generateStructuredGuidance($canonical, $agent);
        }

        // Legacy fallback: template substitution
        $component = $this->inferComponent($canonical);
        $rule = $canonical->preventive_rule ?? $canonical->description;

        return Str::of($template)
            ->replace('{component}', $component)
            ->replace('{preventive_rule}', $rule)
            ->replace('{test_assertion}', $rule)
            ->replace('{validation_check}', $rule)
            ->replace('{design_check}', $rule)
            ->replace('{architectural_constraint}', $rule)
            ->replace('{process_rule}', $rule)
            ->replace('{pattern_id}', $canonical->pattern_id ?? 'N/A')
            ->toString();
    }

    /**
     * Generate structured WHEN/THEN guidance from reflection fields.
     *
     * Derives WHEN condition from feedback, THEN condition from fix,
     * with the preventive_rule as the core lesson text.
     * Falls back to RULE-only format if WHEN/THEN extraction fails.
     */
    private function generateStructuredGuidance(RlmFailure $canonical, string $agent): string
    {
        $rule = $canonical->preventive_rule;
        $when = $canonical->feedback !== null ? Str::limit(trim($canonical->feedback), 200) : null;
        $then = $canonical->fix !== null ? Str::limit(trim($canonical->fix), 200) : null;

        // Best-effort WHEN/THEN derivation
        if ($when !== null && $then !== null) {
            return "WHEN: {$when}\nTHEN: {$then}\nRULE: {$rule}";
        }

        if ($when !== null) {
            return "WHEN: {$when}\nRULE: {$rule}";
        }

        if ($then !== null) {
            return "RULE: {$rule}\nFIX: {$then}";
        }

        // RULE-only fallback
        return "RULE: {$rule}";
    }

    /**
     * Generate a concise title for a distilled lesson.
     */
    private function generateTitle(RlmFailure $canonical, string $agent, int $phase): string
    {
        $prefix = match ($agent) {
            'architect' => 'Build',
            'tester' => 'Test',
            'rlm' => 'Validate',
            'designer' => 'Review',
            'solutions' => 'Design',
            'pm' => 'Process',
            default => 'Check',
        };

        return Str::limit("{$prefix}: {$canonical->title}", 200);
    }

    /**
     * Infer the affected component from a failure's context.
     */
    private function inferComponent(RlmFailure $canonical): string
    {
        return match ($canonical->category) {
            FailureCategory::Scaffolding => 'scaffolded entity code',
            FailureCategory::Filament => 'Filament resource',
            FailureCategory::Testing => 'tests',
            FailureCategory::Laravel => 'Laravel components',
            FailureCategory::Auth => 'authentication/authorization',
            FailureCategory::Process => 'pipeline process',
            FailureCategory::Tailwind => 'UI/Tailwind styles',
            FailureCategory::Configuration => 'configuration',
            FailureCategory::Other => 'application code',
        };
    }

    /**
     * Extract trigger context from a failure's entity_context or category.
     *
     * @return array<string, mixed>|null
     */
    private function extractTriggerContext(RlmFailure $canonical): ?array
    {
        if (! empty($canonical->entity_context)) {
            return $canonical->entity_context;
        }

        return match ($canonical->category) {
            FailureCategory::Scaffolding => ['component' => 'scaffolded-entity'],
            FailureCategory::Filament => ['component' => 'filament-resource'],
            FailureCategory::Testing => ['component' => 'test-suite'],
            FailureCategory::Process => ['component' => 'pipeline'],
            default => null,
        };
    }

    /**
     * Generate a deterministic lesson code from failure code + agent + phase.
     *
     * E.g., BF-001 + architect + 3 → DL-001-A3
     */
    private function generateLessonCode(string $failureCode, string $agent, int $phase): string
    {
        $suffix = mb_strtoupper(mb_substr($agent, 0, 1));
        $failureNum = Str::after($failureCode, 'BF-');

        return "DL-{$failureNum}-{$suffix}{$phase}";
    }
}
