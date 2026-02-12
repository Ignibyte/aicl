<?php

namespace Aicl\Rlm;

use Aicl\Models\GenerationTrace;
use Aicl\Models\RlmScore;

class PatternDiscovery
{
    /**
     * Analyze unprocessed traces for recurring fix patterns.
     *
     * Reads fixes_applied JSON from traces, groups by pattern/fix description,
     * and proposes candidates when the same fix appears across multiple entities.
     *
     * @return PatternCandidate[]
     */
    public function analyzeTraces(int $minOccurrences = 2, float $minConfidence = 0.5): array
    {
        $traces = GenerationTrace::query()
            ->unprocessed()
            ->whereNotNull('fixes_applied')
            ->get()
            ->toArray();

        if ($traces === []) {
            return [];
        }

        // Collect fix patterns across all traces
        $fixGroups = $this->groupFixPatterns($traces);

        $candidates = [];

        foreach ($fixGroups as $key => $group) {
            $occurrences = count($group['entities']);

            if ($occurrences < $minOccurrences) {
                continue;
            }

            // Confidence = occurrences / total traces with fixes
            $tracesWithFixes = count(array_filter($traces, fn (array $t): bool => ! empty($t['fixes_applied'])));
            $confidence = $tracesWithFixes > 0 ? $occurrences / $tracesWithFixes : 0.0;

            if ($confidence < $minConfidence) {
                continue;
            }

            $candidates[] = new PatternCandidate(
                name: 'candidate.'.$this->sanitizeName($key),
                description: $group['description'],
                target: $group['target'] ?? 'model',
                suggestedRegex: $group['regex'] ?? $key,
                severity: 'warning',
                weight: 1.0,
                confidence: round($confidence, 3),
                occurrences: $occurrences,
                source: 'fix_analysis',
            );
        }

        return $candidates;
    }

    /**
     * Analyze score history for patterns that never fail — potential noise.
     *
     * A pattern that passes 100% across multiple entities may be too lenient
     * or testing something that's always true by construction.
     *
     * @return array<int, array{name: string, pass_rate: float, entity_count: int}>
     */
    public function detectStalePatterns(int $minEntities = 3): array
    {
        // Get the latest structural score per entity (UUID PKs — can't use MAX(id))
        $entities = RlmScore::query()
            ->select('entity_name')
            ->where('score_type', 'structural')
            ->whereNotNull('details')
            ->groupBy('entity_name')
            ->pluck('entity_name');

        $scores = $entities->map(
            fn (string $entity) => RlmScore::query()
                ->where('score_type', 'structural')
                ->where('entity_name', $entity)
                ->whereNotNull('details')
                ->latest('created_at')
                ->orderByDesc('id')
                ->first()
        )->filter();

        if ($scores->isEmpty()) {
            return [];
        }

        // Track per-pattern pass rates across entities
        $patternStats = [];

        foreach ($scores as $score) {
            $details = $score->details;
            if (! is_array($details)) {
                continue;
            }

            $entity = $score->entity_name;

            foreach ($details as $detail) {
                $name = $detail['name'] ?? null;
                if (! $name) {
                    continue;
                }

                if (! isset($patternStats[$name])) {
                    $patternStats[$name] = ['entities' => [], 'passes' => 0, 'total' => 0];
                }

                // Only count the latest score per entity per pattern
                if (in_array($entity, $patternStats[$name]['entities'], true)) {
                    continue;
                }

                $patternStats[$name]['entities'][] = $entity;
                $patternStats[$name]['total']++;

                $passed = $detail['passed'] ?? false;
                if ($passed) {
                    $patternStats[$name]['passes']++;
                }
            }
        }

        // Find patterns with 100% pass rate across sufficient entities
        $stale = [];

        foreach ($patternStats as $name => $stats) {
            $entityCount = count($stats['entities']);

            if ($entityCount < $minEntities) {
                continue;
            }

            $passRate = $stats['total'] > 0 ? $stats['passes'] / $stats['total'] : 0.0;

            if ($passRate >= 1.0) {
                $stale[] = [
                    'name' => $name,
                    'pass_rate' => round($passRate, 3),
                    'entity_count' => $entityCount,
                ];
            }
        }

        return $stale;
    }

    /**
     * Mark traces as processed after analysis.
     *
     * @param  array<int, string>  $traceIds
     */
    public function markProcessed(array $traceIds): int
    {
        return GenerationTrace::query()
            ->whereIn('id', $traceIds)
            ->update(['is_processed' => true]);
    }

    /**
     * Export candidates to markdown for human review.
     *
     * @param  PatternCandidate[]  $candidates
     * @return string The path to the exported file
     */
    public function exportCandidates(array $candidates, string $outputDir): string
    {
        if (! is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        $timestamp = date('Y-m-d_His');
        $filename = "candidates_{$timestamp}.md";
        $path = $outputDir.'/'.$filename;

        $md = "# Pattern Discovery Candidates\n\n";
        $md .= '**Generated:** '.date('Y-m-d H:i:s')."\n";
        $md .= '**Candidates:** '.count($candidates)."\n\n";
        $md .= "---\n\n";

        foreach ($candidates as $candidate) {
            $md .= $candidate->toMarkdown()."\n";
        }

        $md .= "---\n\n";
        $md .= "> To promote a candidate to a permanent pattern, add it to `PatternRegistry.php`.\n";
        $md .= "> Candidates are NEVER auto-promoted.\n";

        file_put_contents($path, $md);

        return $path;
    }

    /**
     * Group fix patterns from traces by a normalized key.
     *
     * @param  array<int, array<string, mixed>>  $traces
     * @return array<string, array{description: string, target: string|null, regex: string|null, entities: string[]}>
     */
    protected function groupFixPatterns(array $traces): array
    {
        $groups = [];

        foreach ($traces as $trace) {
            $fixes = $trace['fixes_applied'] ?? null;
            if (! is_array($fixes) || $fixes === []) {
                continue;
            }

            $entity = $trace['entity_name'];

            foreach ($fixes as $fix) {
                $pattern = $fix['pattern'] ?? $fix['check'] ?? $fix['name'] ?? null;
                $fixDesc = $fix['fix'] ?? $fix['description'] ?? $fix['message'] ?? '';
                $target = $fix['target'] ?? null;
                $regex = $fix['regex'] ?? null;

                if (! $pattern) {
                    continue;
                }

                $key = strtolower(trim($pattern));

                if (! isset($groups[$key])) {
                    $groups[$key] = [
                        'description' => $fixDesc ?: "Fix for: {$pattern}",
                        'target' => $target,
                        'regex' => $regex,
                        'entities' => [],
                    ];
                }

                if (! in_array($entity, $groups[$key]['entities'], true)) {
                    $groups[$key]['entities'][] = $entity;
                }
            }
        }

        return $groups;
    }

    /**
     * Sanitize a fix pattern name for use as a pattern candidate name.
     */
    protected function sanitizeName(string $name): string
    {
        $name = strtolower(trim($name));
        $name = preg_replace('/[^a-z0-9_]/', '_', $name);
        $name = preg_replace('/_+/', '_', $name);

        return trim($name, '_');
    }
}
