<?php

namespace Aicl\Console\Commands;

use Aicl\Models\GenerationTrace;
use Aicl\Rlm\PatternDiscovery;
use Illuminate\Console\Command;

class DiscoverPatternsCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'aicl:discover-patterns
        {--stale : Detect stale patterns instead of discovering new ones}
        {--min-occurrences=2 : Minimum trace occurrences for a candidate}
        {--min-confidence=0.5 : Minimum confidence threshold}
        {--output= : Output directory for candidate files}';

    /**
     * @var string
     */
    protected $description = 'Analyze generation traces to discover new pattern candidates or detect stale patterns.';

    public function handle(): int
    {
        $discovery = new PatternDiscovery;

        if ($this->option('stale')) {
            return $this->handleStaleDetection($discovery);
        }

        return $this->handleDiscovery($discovery);
    }

    private function handleDiscovery(PatternDiscovery $discovery): int
    {
        $minOccurrences = (int) $this->option('min-occurrences');
        $minConfidence = (float) $this->option('min-confidence');

        $this->components->info('Analyzing unprocessed traces for recurring fix patterns...');

        $candidates = $discovery->analyzeTraces($minOccurrences, $minConfidence);

        if ($candidates === []) {
            $this->components->info('No pattern candidates found. Need more traces with fixes applied.');

            return self::SUCCESS;
        }

        $this->line('Found '.count($candidates).' candidate pattern(s):');
        $this->newLine();

        $rows = [];
        foreach ($candidates as $candidate) {
            $rows[] = [
                $candidate->name,
                $candidate->target,
                $candidate->suggestedRegex,
                round($candidate->confidence * 100, 1).'%',
                $candidate->occurrences,
            ];
        }

        $this->table(['Name', 'Target', 'Regex', 'Confidence', 'Occurrences'], $rows);

        // Export to markdown
        $outputDir = $this->option('output') ?? base_path('.claude/planning/rlm/candidates');
        $path = $discovery->exportCandidates($candidates, $outputDir);
        $this->newLine();
        $this->line("Candidates exported to: {$path}");

        // Mark traces as processed
        $traceIds = GenerationTrace::query()
            ->unprocessed()
            ->whereNotNull('fixes_applied')
            ->pluck('id')
            ->all();

        if ($traceIds !== []) {
            $count = $discovery->markProcessed($traceIds);
            $this->line("Marked {$count} trace(s) as processed.");
        }

        return self::SUCCESS;
    }

    private function handleStaleDetection(PatternDiscovery $discovery): int
    {
        $this->components->info('Analyzing score history for stale patterns...');

        $stale = $discovery->detectStalePatterns();

        if ($stale === []) {
            $this->components->info('No stale patterns detected. All patterns have caught failures.');

            return self::SUCCESS;
        }

        $this->line('Found '.count($stale).' potentially stale pattern(s):');
        $this->newLine();

        $rows = [];
        foreach ($stale as $pattern) {
            $rows[] = [
                $pattern['name'],
                round($pattern['pass_rate'] * 100, 1).'%',
                $pattern['entity_count'],
            ];
        }

        $this->table(['Pattern', 'Pass Rate', 'Entities'], $rows);

        $this->newLine();
        $this->components->warn('These patterns always pass — they may be too lenient or testing invariants.');
        $this->line('Review whether they still provide value or should be tightened.');

        return self::SUCCESS;
    }
}
