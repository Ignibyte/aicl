<?php

namespace Aicl\Console\Commands;

use Aicl\Components\ComponentRegistry;
use Illuminate\Console\Command;

class PipelineContextCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'aicl:pipeline-context
        {entity : Entity name (e.g., Project, Task)}
        {--phase= : Pipeline phase number (1-8)}
        {--agent= : Agent role (architect, rlm, tester, solutions, designer, pm, docs)}
        {--header : Include pipeline header metadata}
        {--components : Include component recommendations from registry}';

    /**
     * @var string
     */
    protected $description = 'Extract targeted context from a pipeline document — only the phase section relevant to the current agent.';

    public function handle(): int
    {
        $entity = $this->argument('entity');
        $phase = $this->option('phase');
        $agent = $this->option('agent');

        // Find the pipeline document
        $pipelinePath = $this->findPipelineDocument($entity);

        if (! $pipelinePath) {
            $this->components->error("No pipeline document found for entity: {$entity}");
            $this->line('Looked in: .claude/planning/pipeline/active/');

            return self::FAILURE;
        }

        $content = file_get_contents($pipelinePath);

        // Extract header if requested
        if ($this->option('header')) {
            $header = $this->extractHeader($content);
            if ($header) {
                $this->line($header);
                $this->newLine();
            }
        }

        // Include component recommendations if requested
        if ($this->option('components')) {
            $this->outputComponentRecommendations($content, $entity);
        }

        // If a specific phase is requested, extract just that section
        if ($phase) {
            $section = $this->extractPhaseSection($content, (int) $phase);
            if ($section) {
                $this->line($section);
            } else {
                $this->components->warn("Phase {$phase} section not found in pipeline document.");
            }

            return self::SUCCESS;
        }

        // If an agent is specified, extract all phases relevant to that agent
        if ($agent) {
            $phases = $this->getAgentPhases($agent);
            $found = false;

            foreach ($phases as $p) {
                $section = $this->extractPhaseSection($content, $p);
                if ($section) {
                    $this->line($section);
                    $this->newLine();
                    $found = true;
                }
            }

            if (! $found) {
                $this->components->warn("No relevant phase sections found for agent: {$agent}");
            }

            return self::SUCCESS;
        }

        // No filters — output the full document (fallback)
        $this->line($content);

        return self::SUCCESS;
    }

    private function findPipelineDocument(string $entity): ?string
    {
        $activeDir = base_path('.claude/planning/pipeline/active');
        if (! is_dir($activeDir)) {
            return null;
        }

        // Try exact match: PIPELINE-{Entity}.md
        $exact = "{$activeDir}/PIPELINE-{$entity}.md";
        if (file_exists($exact)) {
            return $exact;
        }

        // Try case-insensitive glob
        $pattern = "{$activeDir}/PIPELINE-*.md";
        foreach (glob($pattern) as $file) {
            if (stripos(basename($file), $entity) !== false) {
                return $file;
            }
        }

        return null;
    }

    /**
     * Extract the header metadata table from the pipeline document.
     */
    private function extractHeader(string $content): ?string
    {
        // Header is everything up to the first "---" separator after the title
        if (preg_match('/^(# .+?\n\n\|.+?\|.+?\n---)/s', $content, $matches)) {
            return $matches[1];
        }

        // Fallback: just the title line and metadata table
        $lines = explode("\n", $content);
        $header = [];
        $inTable = false;

        foreach ($lines as $line) {
            if (str_starts_with($line, '# ')) {
                $header[] = $line;

                continue;
            }
            if (str_starts_with($line, '|')) {
                $inTable = true;
                $header[] = $line;

                continue;
            }
            if ($inTable && $line === '') {
                break;
            }
            if ($inTable) {
                $header[] = $line;
            }
        }

        return $header !== [] ? implode("\n", $header) : null;
    }

    /**
     * Extract a specific phase section by phase number.
     */
    private function extractPhaseSection(string $content, int $phase): ?string
    {
        // Phase sections start with "## Phase N:" or "## Phase N.5:"
        // They end at the next "## Phase" or "---" followed by "## Phase" or end of file
        $pattern = '/^(## Phase '.$phase.'[\.\d]*:.+?)(?=^---\s*$\n^## Phase|\z)/ms';

        if (preg_match($pattern, $content, $matches)) {
            return trim($matches[1]);
        }

        // Try simpler pattern (Phase N without sub-sections like 3.5)
        $lines = explode("\n", $content);
        $capture = false;
        $section = [];
        $phaseStr = "## Phase {$phase}";

        foreach ($lines as $line) {
            if (str_starts_with($line, $phaseStr)) {
                $capture = true;
                $section[] = $line;

                continue;
            }

            if ($capture) {
                // Stop at next phase heading or separator before next phase
                if (preg_match('/^## Phase \d/', $line)) {
                    break;
                }
                if ($line === '---' && $section !== []) {
                    // Check if next non-empty line is a new phase — stop here
                    break;
                }
                $section[] = $line;
            }
        }

        return $section !== [] ? trim(implode("\n", $section)) : null;
    }

    /**
     * Map agent roles to their relevant pipeline phases.
     *
     * @return int[]
     */
    private function getAgentPhases(string $agent): array
    {
        return match ($agent) {
            'pm' => [1],
            'solutions' => [2],
            'architect' => [3, 5],
            'designer' => [4],  // Phase 3.5 extracted via phase 3's section
            'rlm' => [4, 6],
            'tester' => [4, 6, 7],
            'docs' => [8],
            default => [],
        };
    }

    /**
     * Output component recommendations for an entity based on its pipeline spec fields.
     */
    private function outputComponentRecommendations(string $content, string $entity): void
    {
        // Extract fields from the Phase 1 spec section
        $fields = $this->extractFieldsFromSpec($content);

        if ($fields === []) {
            $this->components->warn('No fields found in pipeline spec for component recommendations.');

            return;
        }

        try {
            $registry = app(ComponentRegistry::class);
        } catch (\Throwable) {
            $this->components->warn('ComponentRegistry not available.');

            return;
        }

        $this->info("Component Recommendations for {$entity}");
        $this->newLine();

        // Context rules
        $this->line('Context Rules:');
        $this->line('  blade/livewire → Use <x-aicl-*> components');
        $this->line('  filament-form  → Use Filament form components (NOT <x-aicl-*>)');
        $this->line('  filament-table → Use Filament table columns (NOT <x-aicl-*>)');
        $this->line('  filament-widget → Can use <x-aicl-*> in widget Blade views');
        $this->newLine();

        // Field-specific recommendations
        foreach (['index', 'show', 'card'] as $viewType) {
            $recommendations = $registry->recommendForEntity($fields, 'blade', $viewType);
            if ($recommendations === []) {
                continue;
            }

            $this->info("  View type: {$viewType}");
            $rows = [];
            foreach ($recommendations as $rec) {
                $rows[] = [
                    $rec->tag,
                    number_format($rec->confidence, 2),
                    $rec->reason,
                    $rec->alternative ?? '-',
                ];
            }
            $this->table(['Component', 'Confidence', 'Reason', 'Filament Alternative'], $rows);
            $this->newLine();
        }

        // Available categories
        $this->line('Available categories: '.implode(', ', $registry->categories()));
        $this->line("Total components: {$registry->count()}");
        $this->newLine();
    }

    /**
     * Extract field definitions from the Phase 1 spec in the pipeline document.
     *
     * @return array<string, string>
     */
    private function extractFieldsFromSpec(string $content): array
    {
        $fields = [];

        // Look for field definitions in the spec (various formats)
        // Format 1: "- Fields: name:string, description:text:nullable, ..."
        if (preg_match('/Fields:\s*(.+)/i', $content, $matches)) {
            $fieldLine = trim($matches[1]);
            foreach (explode(',', $fieldLine) as $field) {
                $parts = explode(':', trim($field), 3);
                if (count($parts) >= 2) {
                    $fields[$parts[0]] = $parts[1];
                }
            }
        }

        // Format 2: "--fields=" in scaffolder command
        if (preg_match('/--fields="([^"]+)"/', $content, $matches)) {
            foreach (explode(',', $matches[1]) as $field) {
                $parts = explode(':', trim($field), 3);
                if (count($parts) >= 2) {
                    $fields[$parts[0]] = $parts[1];
                }
            }
        }

        return $fields;
    }
}
