<?php

namespace Aicl\Console\Commands;

use Aicl\Models\DistilledLesson;
use Aicl\Models\RlmFailure;
use Aicl\Rlm\EntityValidator;
use Aicl\Rlm\PatternRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ValidateEntityCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'aicl:validate
        {name : The entity name to validate (e.g., Project, Task)}
        {--target=* : Target file types to validate (e.g., model, migration, factory, filament, policy, observer, test, api)}
        {--quick : Output single-line pass/fail format for inline assertions}';

    /**
     * @var string
     */
    protected $description = 'Validate AI-generated entity code against AICL patterns and return a quality score.';

    public function handle(): int
    {
        $name = Str::studly($this->argument('name'));
        $pluralName = Str::pluralStudly($name);
        $tableName = Str::snake($pluralName);
        $targets = $this->option('target');
        $quick = (bool) $this->option('quick');

        $patternVersion = PatternRegistry::currentVersion();

        if (! $quick) {
            $this->components->info("Validating entity: {$name}");
            $this->newLine();
        }

        $validator = new EntityValidator($name, $patternVersion);

        // Register files that exist — check app/ first (generated entities), then package (golden entity)
        $fileMap = [
            'model' => file_exists(app_path("Models/{$name}.php"))
                ? app_path("Models/{$name}.php")
                : base_path("packages/aicl/src/Models/{$name}.php"),
            'policy' => file_exists(app_path("Policies/{$name}Policy.php"))
                ? app_path("Policies/{$name}Policy.php")
                : base_path("packages/aicl/src/Policies/{$name}Policy.php"),
            'observer' => file_exists(app_path("Observers/{$name}Observer.php"))
                ? app_path("Observers/{$name}Observer.php")
                : base_path("packages/aicl/src/Observers/{$name}Observer.php"),
            'factory' => database_path("factories/{$name}Factory.php"),
            'filament' => app_path("Filament/Resources/{$pluralName}/{$name}Resource.php"),
            'form' => file_exists(app_path("Filament/Resources/{$pluralName}/Schemas/{$name}Form.php"))
                ? app_path("Filament/Resources/{$pluralName}/Schemas/{$name}Form.php")
                : base_path("packages/aicl/src/Filament/Resources/{$pluralName}/Schemas/{$name}Form.php"),
            'infolist' => file_exists(app_path("Filament/Resources/{$pluralName}/Schemas/{$name}Infolist.php"))
                ? app_path("Filament/Resources/{$pluralName}/Schemas/{$name}Infolist.php")
                : base_path("packages/aicl/src/Filament/Resources/{$pluralName}/Schemas/{$name}Infolist.php"),
            'test' => file_exists(base_path("tests/Feature/Entities/{$name}Test.php"))
                ? base_path("tests/Feature/Entities/{$name}Test.php")
                : base_path("tests/Feature/{$name}Test.php"),
        ];

        // Find migration
        $migrationPattern = database_path("migrations/*_create_{$tableName}_table.php");
        $migrations = glob($migrationPattern);
        if (! empty($migrations)) {
            $fileMap['migration'] = $migrations[0];
        }

        // Blade view files (for component patterns C01-C10 and view patterns V01-V08)
        $snakeName = Str::snake($name);
        $indexView = resource_path("views/{$snakeName}/index.blade.php");
        $showView = resource_path("views/{$snakeName}/show.blade.php");
        if (file_exists($indexView)) {
            $fileMap['blade_view'] = $indexView;
            $fileMap['blade_index'] = $indexView;
        } elseif (file_exists($showView)) {
            $fileMap['blade_view'] = $showView;
        }
        if (file_exists($showView)) {
            $fileMap['blade_show'] = $showView;
        }

        // ViewController (for V07 pattern)
        $viewController = app_path("Http/Controllers/{$name}ViewController.php");
        if (file_exists($viewController)) {
            $fileMap['view_controller'] = $viewController;
        }

        // Widget Blade views
        $widgetViews = glob(resource_path("views/filament/widgets/{$snakeName}*.blade.php"));
        if (! empty($widgetViews)) {
            $fileMap['blade_widget'] = $widgetViews[0];
        }

        // Registration files (for Phase 6 re-validation patterns)
        $fileMap['app_service_provider'] = app_path('Providers/AppServiceProvider.php');
        $fileMap['api_routes'] = base_path('routes/api.php');
        $fileMap['admin_panel_provider'] = app_path('Providers/Filament/AdminPanelProvider.php');

        $foundFiles = 0;
        foreach ($fileMap as $target => $path) {
            if (file_exists($path)) {
                $validator->addFile($target, $path);
                $foundFiles++;
            }
        }

        if ($foundFiles === 0) {
            if ($quick) {
                $this->line("FAIL: {$name} — no files found");
            } else {
                $this->components->error("No files found for entity: {$name}");
            }

            return self::FAILURE;
        }

        // Run validation (filtered by targets if provided)
        $validator->validate($targets !== [] ? $targets : null);
        $score = $validator->score();

        if ($quick) {
            return $this->renderQuickOutput($validator, $targets);
        }

        // ─── Enriched Score Card Header ────────────────────────────────
        $this->line("  <fg=cyan;options=bold>Entity:</> {$name} — <fg=cyan>Pattern Set:</> {$patternVersion}");

        // Display results table
        $rows = [];
        foreach ($validator->results() as $result) {
            $status = match (true) {
                $result->waived => '<fg=blue>WAIVED</>',
                $result->passed => '<fg=green>PASS</>',
                $result->pattern->isError() => '<fg=red>FAIL</>',
                default => '<fg=yellow>WARN</>',
            };
            $rows[] = [
                $status,
                $result->pattern->name,
                $result->pattern->description,
                $result->pattern->weight,
            ];
        }

        $this->table(['Status', 'Pattern', 'Description', 'Weight'], $rows);

        $this->newLine();

        // ─── Convention (L1) Score ──────────────────────────────────────
        $total = count($validator->results());
        $passed = count(array_filter($validator->results(), fn ($r) => $r->passed));
        $errors = count($validator->errors());
        $warnings = count($validator->warnings());
        $scoreColor = $score >= 80 ? 'green' : ($score >= 60 ? 'yellow' : 'red');

        $conventionLine = "  Conventions (L1):  <fg={$scoreColor};options=bold>{$passed}/{$total} ({$score}%)</>";
        if ($validator->waivedCount() > 0) {
            $remaining = $validator->remainingBudget();
            $conventionLine .= " — <fg=blue>{$validator->waivedCount()} WAIVED</> (budget: {$remaining} remaining)";
        }
        $this->line($conventionLine);

        // Frontend patterns sub-score (component + view patterns)
        $frontendResults = array_filter(
            $validator->results(),
            fn ($r) => str_starts_with($r->pattern->name, 'component.') || str_starts_with($r->pattern->name, 'view.'),
        );
        if ($frontendResults !== []) {
            $fTotal = 0.0;
            $fEarned = 0.0;
            foreach ($frontendResults as $r) {
                $fTotal += $r->pattern->weight;
                if ($r->passed) {
                    $fEarned += $r->pattern->weight;
                }
            }
            $fScore = $fTotal > 0 ? round(($fEarned / $fTotal) * 100, 1) : 100.0;
            $fPassed = count(array_filter($frontendResults, fn ($r) => $r->passed));
            $fCount = count($frontendResults);
            $fColor = $fScore >= 80 ? 'green' : ($fScore >= 60 ? 'yellow' : 'red');
            $this->line("  Frontend patterns: <fg={$fColor}>{$fPassed}/{$fCount}</> ({$fScore}%)");
        }

        // ─── File Count ────────────────────────────────────────────────
        $this->line("  Files:             {$foundFiles} registered");

        // ─── Learning Summary ──────────────────────────────────────────
        $this->renderLearningSummary($name);

        // ─── Summary ───────────────────────────────────────────────────
        $this->line("  Passed: {$passed} | Errors: {$errors} | Warnings: {$warnings}");

        if ($validator->hasVersionWarning()) {
            $this->line('  <fg=yellow>⚠ Using unpinned (latest) pattern set — consider pinning to a specific version</>');
        }

        $this->newLine();

        if ($errors > 0) {
            $this->components->error("{$errors} error(s) must be fixed before this entity is production-ready.");

            foreach ($validator->errors() as $error) {
                $this->line("    <fg=red>x</> {$error->pattern->description}");
            }

            $this->newLine();

            return self::FAILURE;
        }

        if ($warnings > 0) {
            $this->components->warn("{$warnings} warning(s) — consider addressing these for best practices.");
        } else {
            $this->components->info("Entity {$name} passes all AICL validation patterns!");
        }

        return self::SUCCESS;
    }

    /**
     * Render quick single-line pass/fail output for inline assertions.
     *
     * @param  array<int, string>  $targets
     */
    private function renderQuickOutput(EntityValidator $validator, array $targets): int
    {
        $results = $validator->results();
        $hasFailure = false;

        if ($targets !== []) {
            // Group results by target and show per-target line
            $byTarget = [];
            foreach ($results as $result) {
                $target = $result->pattern->target;
                $byTarget[$target][] = $result;
            }

            foreach ($targets as $target) {
                if (! isset($byTarget[$target])) {
                    $this->line("SKIP: {$target} — no patterns matched");

                    continue;
                }

                $targetResults = $byTarget[$target];
                $total = count($targetResults);
                $passed = count(array_filter($targetResults, fn ($r) => $r->passed));

                if ($passed === $total) {
                    $this->line("PASS: {$target} ({$passed}/{$total})");
                } else {
                    $hasFailure = true;
                    $failures = array_filter($targetResults, fn ($r) => ! $r->passed);
                    $failNames = implode(', ', array_map(fn ($r) => $r->pattern->name, $failures));
                    $this->line("FAIL: {$target} ({$passed}/{$total}) — {$failNames}");
                }
            }
        } else {
            // No targets specified — show overall single line
            $total = count($results);
            $passed = count(array_filter($results, fn ($r) => $r->passed));

            if ($passed === $total) {
                $this->line("PASS: all ({$passed}/{$total})");
            } else {
                $hasFailure = true;
                $failures = array_filter($results, fn ($r) => ! $r->passed);
                $failNames = implode(', ', array_map(fn ($r) => $r->pattern->name, $failures));
                $this->line("FAIL: all ({$passed}/{$total}) — {$failNames}");
            }
        }

        return $hasFailure ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Render learning summary — instructions recalled + entity failures.
     */
    private function renderLearningSummary(string $entityName): void
    {
        try {
            $instructionCount = DistilledLesson::query()
                ->where('is_active', true)
                ->count();

            $entityFailures = RlmFailure::query()
                ->where('entity_name', $entityName)
                ->where('is_active', true)
                ->count();

            $parts = [];
            $parts[] = "{$instructionCount} active lessons";
            $parts[] = "{$entityFailures} entity failures";

            $this->line('  Learning:          '.implode(', ', $parts));
        } catch (\Throwable) {
            // Tables may not exist yet
        }
    }
}
