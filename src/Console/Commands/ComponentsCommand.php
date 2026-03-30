<?php

declare(strict_types=1);

namespace Aicl\Console\Commands;

use Aicl\Components\ComponentDefinition;
use Aicl\Components\ComponentRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

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

        $category = $this->option('category');
        if ($category) {
            $components = $registry->forCategory($category);
        }

        $context = $this->option('context');
        if ($context) {
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

        $this->printComponentDetails($component);
        $this->printComponentSections($component);

        return self::SUCCESS;
    }

    /**
     * Print the core details of a component.
     */
    private function printComponentDetails(ComponentDefinition $component): void
    {
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
    }

    /**
     * Print component props, slots, and variants sections.
     */
    private function printComponentSections(ComponentDefinition $component): void
    {
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

        if (count($component->slots) > 0) {
            $this->newLine();
            $this->info('  Slots:');
            foreach ($component->slots as $name => $slot) {
                $this->line("    {$name}: ".($slot['description'] ?? 'No description'));
            }
        }

        if (count($component->variants) > 0) {
            $this->newLine();
            $this->info('  Variants:');
            foreach ($component->variants as $name => $variant) {
                $this->line("    {$name}: ".($variant['description'] ?? 'No description'));
            }
        }
    }

    private function handleValidate(ComponentRegistry $registry): int
    {
        $components = $registry->all();
        $errors = $registry->discovery()->errors();
        $hasErrors = false;

        $this->info("Validating {$components->count()} component manifests...");
        $this->newLine();

        foreach ($components as $component) {
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

        $markdown = $this->buildTreeMarkdown($components, $grouped, $order);

        // Write to file
        $docsPath = dirname(__DIR__, 3).'/resources/docs/component-decision-tree.md';
        $this->ensureDirectoryExists(dirname($docsPath));
        file_put_contents($docsPath, $markdown);

        // Console output (compact tree view)
        $this->printTreeConsoleOutput($components, $grouped, $order);
        $this->info("Decision tree written to: {$docsPath}");

        return self::SUCCESS;
    }

    /**
     * Build the full Markdown content for the decision tree document.
     *
     * @param Collection<string, ComponentDefinition>                  $components
     * @param Collection<string, Collection<int, ComponentDefinition>> $grouped
     * @param array<int, string>                                       $order
     */
    private function buildTreeMarkdown($components, $grouped, array $order): string
    {
        $lines = [];
        $lines[] = '# AICL Component Decision Tree';
        $lines[] = '';
        $lines[] = '> Auto-generated from `component.json` manifests via `artisan aicl:components tree`';
        $lines[] = '> Generated: '.now()->toDateTimeString();
        $lines[] = '> Components: '.$components->count();
        $lines[] = '';

        $this->buildCategoryMarkdown($grouped, $order, $lines);
        $this->buildCompositionHierarchy($components, $lines);
        $this->buildContextCrosswalk($components, $lines);

        return implode("\n", $lines);
    }

    /**
     * Build markdown for each category section.
     *
     * @param Collection<string, Collection<int, ComponentDefinition>> $grouped
     * @param array<int, string>                                       $order
     * @param array<int, string>                                       $lines
     */
    private function buildCategoryMarkdown($grouped, array $order, array &$lines): void
    {
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
    }

    /**
     * Build the composition hierarchy markdown section.
     *
     * @param Collection<string, ComponentDefinition> $components
     * @param array<int, string>                      $lines
     */
    private function buildCompositionHierarchy($components, array &$lines): void
    {
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
    }

    /**
     * Build the context crosswalk markdown table.
     *
     * @param Collection<string, ComponentDefinition> $components
     * @param array<int, string>                      $lines
     */
    private function buildContextCrosswalk($components, array &$lines): void
    {
        $lines[] = '## Context Crosswalk';
        $lines[] = '';
        $lines[] = '| Component | blade | livewire | filament-widget | filament-form | filament-table | email | pdf |';
        $lines[] = '|-----------|:-----:|:--------:|:---------------:|:-------------:|:--------------:|:-----:|:---:|';
        $contexts = ['blade', 'livewire', 'filament-widget', 'filament-form', 'filament-table', 'email', 'pdf'];

        foreach ($components as $c) {
            $row = "`{$c->tag}`";
            foreach ($contexts as $ctx) {
                $row .= $c->supportsContext($ctx) && ! $c->isExcludedFrom($ctx) ? ' | Y' : ' | -';
            }
            $lines[] = $row.' |';
        }
        $lines[] = '';
    }

    /**
     * Print the compact tree view to the console.
     *
     * @param Collection<string, ComponentDefinition>                  $components
     * @param Collection<string, Collection<int, ComponentDefinition>> $grouped
     * @param array<int, string>                                       $order
     */
    private function printTreeConsoleOutput($components, $grouped, array $order): void
    {
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
    }

    /** @codeCoverageIgnore Reason: external-service -- Directory creation only runs when docs dir is missing */
    private function ensureDirectoryExists(string $dir): void
    {
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
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

            return self::SUCCESS;
        }

        $this->warn('No cache file found.');

        return self::SUCCESS;
    }

    /** @codeCoverageIgnore Reason: external-service -- Unknown subcommand error path */
    private function handleUnknown(): int
    {
        $this->error('Unknown action. Available: list, show, validate, recommend, tree, cache, clear');

        return self::FAILURE;
    }
}
