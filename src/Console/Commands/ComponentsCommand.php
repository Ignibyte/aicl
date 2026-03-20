<?php

declare(strict_types=1);

namespace Aicl\Console\Commands;

use Aicl\Components\ComponentDefinition;
use Aicl\Components\ComponentRegistry;
use Illuminate\Console\Command;

/**
 * CLI for the component registry: list, show, validate, recommend, cache, clear.
 */
class ComponentsCommand extends Command
{
    protected $signature = 'aicl:components
        {action=list : Action to perform: list, show, validate, recommend, tree, cache, clear}
        {--tag= : Component tag for show action}
        {--category= : Filter by category for list action}
        {--context= : Filter by context for list action}
        {--fields= : Comma-separated name:type pairs for recommend action}
        {--view-type=index : View type for recommend: index, show, card}
        {--format=table : Output format: table, json}';

    protected $description = 'Manage and query the AICL component registry';

    public function handle(ComponentRegistry $registry): int
    {
        return match ($this->argument('action')) {
            'list' => $this->handleList($registry),
            'show' => $this->handleShow($registry),
            'validate' => $this->handleValidate($registry),
            'recommend' => $this->handleRecommend($registry),
            'tree' => $this->handleTree($registry),
            'cache' => $this->handleCache($registry),
            'clear' => $this->handleClear($registry),
            default => $this->handleUnknown(),
        };
    }

    private function handleList(ComponentRegistry $registry): int
    {
        $components = $registry->all();

        if ($category = $this->option('category')) {
            $components = $registry->forCategory($category);
        }

        if ($context = $this->option('context')) {
            $components = $registry->forContext($context);
        }

        if ($components->isEmpty()) {
            $this->warn('No components found.');

            return self::SUCCESS;
        }

        if ($this->option('format') === 'json') {
            $this->line((string) json_encode($components->map->toArray()->values(), JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        $this->info("AICL Component Registry ({$components->count()} components)");
        $this->newLine();

        $rows = $components->map(fn (ComponentDefinition $c): array => [
            $c->tag,
            $c->category,
            $c->status,
            $c->source,
            mb_substr($c->description, 0, 60).(mb_strlen($c->description) > 60 ? '...' : ''),
        ])->values()->all();

        $this->table(['Tag', 'Category', 'Status', 'Source', 'Description'], $rows);

        // Summary by category
        $this->newLine();
        $categories = $components->groupBy('category');
        foreach ($categories as $cat => $items) {
            $this->line("  {$cat}: {$items->count()} components");
        }

        return self::SUCCESS;
    }

    private function handleShow(ComponentRegistry $registry): int
    {
        $tag = $this->option('tag');
        if (! $tag) {
            $this->error('--tag is required for show action');

            return self::FAILURE;
        }

        $component = $registry->get($tag);
        if ($component === null) {
            $this->error("Component '{$tag}' not found");

            return self::FAILURE;
        }

        if ($this->option('format') === 'json') {
            $this->line((string) json_encode($component->toArray(), JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        $this->info($component->name);
        $this->line("  Tag: {$component->tag}");
        $this->line("  Category: {$component->category}");
        $this->line("  Status: {$component->status}");
        $this->line("  Source: {$component->source}");
        $this->line("  Class: {$component->class}");
        $this->line("  Description: {$component->description}");
        $this->line("  Decision Rule: {$component->decisionRule}");
        $this->newLine();

        if (count($component->context) > 0) {
            $this->line('  Contexts: '.implode(', ', $component->context));
        }
        if (count($component->notFor) > 0) {
            $this->line('  Not For: '.implode(', ', $component->notFor));
        }
        if (count($component->composableIn) > 0) {
            $this->line('  Composable In: '.implode(', ', $component->composableIn));
        }
        if (count($component->fieldSignals) > 0) {
            $this->line('  Field Signals: '.implode(', ', $component->fieldSignals));
        }

        if ($component->filamentEquivalent) {
            $this->newLine();
            $this->line('  Filament Equivalent:');
            $this->line("    Context: {$component->filamentEquivalent['context']}");
            $this->line("    Class: {$component->filamentEquivalent['class']}");
            if (isset($component->filamentEquivalent['note'])) {
                $this->line("    Note: {$component->filamentEquivalent['note']}");
            }
        }

        // Props
        if (count($component->props) > 0) {
            $this->newLine();
            $this->info('  Props:');
            $propRows = [];
            foreach ($component->props as $name => $prop) {
                $propRows[] = [
                    $name,
                    $prop['type'] ?? 'mixed',
                    ($prop['required'] ?? false) ? 'Yes' : 'No',
                    $prop['default'] ?? '-',
                    mb_substr($prop['description'] ?? '', 0, 50),
                ];
            }
            $this->table(['Name', 'Type', 'Required', 'Default', 'Description'], $propRows);
        }

        // Slots
        if (count($component->slots) > 0) {
            $this->newLine();
            $this->info('  Slots:');
            foreach ($component->slots as $name => $slot) {
                $this->line("    {$name}: ".($slot['description'] ?? 'No description'));
            }
        }

        // Variants
        if (count($component->variants) > 0) {
            $this->newLine();
            $this->info('  Variants:');
            foreach ($component->variants as $name => $variant) {
                $this->line("    {$name}: ".($variant['description'] ?? 'No description'));
            }
        }

        return self::SUCCESS;
    }

    private function handleValidate(ComponentRegistry $registry): int
    {
        $components = $registry->all();
        $errors = $registry->discovery()->errors();
        $hasErrors = false;

        $this->info("Validating {$components->count()} component manifests...");
        $this->newLine();

        foreach ($components as $tag => $component) {
            $this->line("  ✓ {$component->tag} ({$component->source})");
        }

        if (count($errors) > 0) {
            $hasErrors = true;
            $this->newLine();
            $this->error('Validation errors:');
            foreach ($errors as $dir => $dirErrors) {
                $this->line("  ✗ {$dir}:");
                foreach ($dirErrors as $error) {
                    $this->line("    - {$error}");
                }
            }
        }

        $this->newLine();
        if ($hasErrors) {
            $this->error('Validation failed with errors.');

            return self::FAILURE;
        }

        $this->info('All component manifests are valid.');

        return self::SUCCESS;
    }

    private function handleRecommend(ComponentRegistry $registry): int
    {
        $fieldsOption = $this->option('fields');
        if (! $fieldsOption) {
            $this->error('--fields is required for recommend action (e.g., --fields="title:string,status:enum,budget:float")');

            return self::FAILURE;
        }

        $fields = [];
        foreach (explode(',', $fieldsOption) as $field) {
            $parts = explode(':', trim($field), 2);
            if (count($parts) === 2) {
                $fields[$parts[0]] = $parts[1];
            }
        }

        $viewType = (string) ($this->option('view-type') ?? 'index');
        $recommendations = $registry->recommendForEntity($fields, 'blade', $viewType);

        if (count($recommendations) === 0) {
            $this->warn('No component recommendations for the given fields.');

            return self::SUCCESS;
        }

        $this->info("Component Recommendations (view: {$viewType})");
        $this->newLine();

        $rows = [];
        foreach ($recommendations as $rec) {
            $rows[] = [
                $rec->tag,
                number_format($rec->confidence, 2),
                $rec->reason,
                $rec->alternative ?? '-',
            ];
        }

        $this->table(['Component', 'Confidence', 'Reason', 'Alternative'], $rows);

        return self::SUCCESS;
    }

    private function handleTree(ComponentRegistry $registry): int
    {
        $components = $registry->all();

        if ($components->isEmpty()) {
            $this->warn('No components registered.');

            return self::SUCCESS;
        }

        $grouped = $components->groupBy('category');
        $order = ['metric', 'data', 'collection', 'action', 'status', 'timeline', 'layout', 'feedback', 'utility'];

        // Build tree content for both console and file output
        $lines = [];
        $lines[] = '# AICL Component Decision Tree';
        $lines[] = '';
        $lines[] = '> Auto-generated from `component.json` manifests via `artisan aicl:components tree`';
        $lines[] = '> Generated: '.now()->toDateTimeString();
        $lines[] = '> Components: '.$components->count();
        $lines[] = '';

        foreach ($order as $category) {
            if (! isset($grouped[$category])) {
                continue;
            }

            $items = $grouped[$category];
            $lines[] = "## {$category} ({$items->count()})";
            $lines[] = '';

            foreach ($items as $c) {
                $lines[] = "### `{$c->tag}`";
                $lines[] = '';
                $lines[] = "- **Decision Rule:** {$c->decisionRule}";
                $lines[] = '- **Context:** '.implode(', ', $c->context);
                if (count($c->notFor) > 0) {
                    $lines[] = '- **Not For:** '.implode(', ', $c->notFor);
                }
                if (count($c->fieldSignals) > 0) {
                    $lines[] = '- **Field Signals:** '.implode(', ', $c->fieldSignals);
                }
                if (count($c->composableIn) > 0) {
                    $lines[] = '- **Composable In:** '.implode(', ', $c->composableIn);
                }
                if ($c->filamentEquivalent) {
                    $equiv = $c->filamentEquivalent;
                    $lines[] = "- **Filament Equivalent:** `{$equiv['class']}` ({$equiv['note']})";
                }
                $lines[] = '';
            }
        }

        // Add composition hierarchy
        $lines[] = '## Composition Hierarchy';
        $lines[] = '';
        $lines[] = 'Components define which parents they can be nested in via `composable_in`:';
        $lines[] = '';
        $parentMap = [];
        foreach ($components as $c) {
            foreach ($c->composableIn as $parent) {
                $parentMap[$parent][] = $c->tag;
            }
        }
        ksort($parentMap);
        foreach ($parentMap as $parent => $children) {
            $lines[] = "- **`{$parent}`** accepts: ".implode(', ', array_map(fn ($t) => "`{$t}`", $children));
        }
        $lines[] = '';

        // Add context crosswalk
        $lines[] = '## Context Crosswalk';
        $lines[] = '';
        $lines[] = '| Component | blade | livewire | filament-widget | filament-form | filament-table | email | pdf |';
        $lines[] = '|-----------|:-----:|:--------:|:---------------:|:-------------:|:--------------:|:-----:|:---:|';
        foreach ($components as $c) {
            $contexts = ['blade', 'livewire', 'filament-widget', 'filament-form', 'filament-table', 'email', 'pdf'];
            $row = "`{$c->tag}`";
            foreach ($contexts as $ctx) {
                if ($c->isExcludedFrom($ctx)) {
                    $row .= ' | -';
                } elseif ($c->supportsContext($ctx)) {
                    $row .= ' | Y';
                } else {
                    $row .= ' | -';
                }
            }
            $lines[] = $row.' |';
        }
        $lines[] = '';

        $markdown = implode("\n", $lines);

        // Write to file
        $docsPath = dirname(__DIR__, 3).'/resources/docs/component-decision-tree.md';
        $docsDir = dirname($docsPath);
        if (! is_dir($docsDir)) {
            mkdir($docsDir, 0755, true);
        }
        file_put_contents($docsPath, $markdown);

        // Console output (compact tree view)
        $this->info('AICL Component Decision Tree');
        $this->line("(Auto-generated from {$components->count()} component.json manifests)");
        $this->newLine();

        foreach ($order as $category) {
            if (! isset($grouped[$category])) {
                continue;
            }

            $items = $grouped[$category];
            $this->line("├── {$category} ({$items->count()})");
            $items->each(function (ComponentDefinition $c, string $key) use ($items): void {
                $isLast = $key === $items->keys()->last();
                $prefix = $isLast ? '│   └── ' : '│   ├── ';
                $this->line("{$prefix}{$c->tag}");
                $this->line("{$prefix}    Rule: {$c->decisionRule}");
                if (count($c->fieldSignals) > 0) {
                    $this->line("{$prefix}    Signals: ".implode(', ', $c->fieldSignals));
                }
            });
            $this->newLine();
        }

        $this->info("Decision tree written to: {$docsPath}");

        return self::SUCCESS;
    }

    private function handleCache(ComponentRegistry $registry): int
    {
        $path = $registry->writeCache();
        $this->info("Component registry cached to: {$path}");
        $this->line("Components cached: {$registry->count()}");

        return self::SUCCESS;
    }

    private function handleClear(ComponentRegistry $registry): int
    {
        if ($registry->clearCache()) {
            $this->info('Component registry cache cleared.');
        } else {
            $this->warn('No cache file found.');
        }

        return self::SUCCESS;
    }

    private function handleUnknown(): int
    {
        $this->error('Unknown action. Available: list, show, validate, recommend, tree, cache, clear');

        return self::FAILURE;
    }
}
