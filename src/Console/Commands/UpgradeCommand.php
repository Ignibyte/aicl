<?php

namespace Aicl\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

class UpgradeCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'aicl:upgrade
        {--force : Apply changes without confirmation}
        {--section= : Only upgrade a specific section (agents, rlm, pipeline, tests, config, claude, planning)}
        {--diff : Show file-level diffs for changed files}
        {--fresh : Ignore state file, treat everything as needing update}';

    /**
     * @var string
     */
    protected $description = 'Synchronize project-level files with the installed AICL package version.';

    protected Filesystem $files;

    protected string $packageRoot;

    protected string $projectRoot;

    /** @var array<string, mixed> */
    protected array $state;

    /** @var array{updated: int, removed: int, skipped: int, up_to_date: int} */
    protected array $summary = [
        'updated' => 0,
        'removed' => 0,
        'skipped' => 0,
        'up_to_date' => 0,
    ];

    public function handle(Filesystem $files): int
    {
        $this->files = $files;
        $this->projectRoot = base_path();
        $this->packageRoot = $this->resolvePackageRoot();

        $manifest = $this->loadManifest();
        if ($manifest === null) {
            return self::FAILURE;
        }

        $this->state = $this->loadState();
        $manifestVersion = $manifest['version'];
        $currentVersion = $this->state['package_version'] ?? 'unknown';
        $isForce = (bool) $this->option('force');
        $isFresh = (bool) $this->option('fresh');

        // Check if already up to date
        if (! $isFresh && $currentVersion === $manifestVersion && ! $isForce) {
            $this->components->info("Already up to date with v{$manifestVersion}.");

            return self::SUCCESS;
        }

        $versionLabel = $currentVersion === 'unknown'
            ? "Initial sync → v{$manifestVersion}"
            : "v{$currentVersion} → v{$manifestVersion}";

        if ($isForce) {
            $this->components->info("AICL Upgrade — {$versionLabel} (applying changes)");
        } else {
            $this->components->info("AICL Upgrade — {$versionLabel} (dry-run)");
        }

        $this->newLine();

        // Filter sections if requested
        $sections = $manifest['sections'];
        $sectionFilter = $this->option('section');
        if ($sectionFilter) {
            if (! isset($sections[$sectionFilter])) {
                $this->components->error("Unknown section: {$sectionFilter}");
                $this->components->info('Available sections: '.implode(', ', array_keys($sections)));

                return self::FAILURE;
            }
            $sections = [$sectionFilter => $sections[$sectionFilter]];
        }

        // Process each section
        foreach ($sections as $sectionKey => $section) {
            $this->processSection($sectionKey, $section, $isForce, $isFresh);
        }

        // Update state file
        if ($isForce) {
            $this->state['package_version'] = $manifestVersion;
            $this->state['last_upgraded'] = now()->toIso8601String();
            $this->writeState();

            // Clear cached version strings so they reflect the upgraded version
            \Illuminate\Support\Facades\Cache::forget('aicl.version.framework');
            \Illuminate\Support\Facades\Cache::forget('aicl.version.project');
        }

        // Summary
        $this->newLine();
        $parts = [];
        if ($this->summary['updated'] > 0) {
            $parts[] = "{$this->summary['updated']} files ".($isForce ? 'updated' : 'to update');
        }
        if ($this->summary['removed'] > 0) {
            $parts[] = "{$this->summary['removed']} files ".($isForce ? 'removed' : 'to remove');
        }
        if ($this->summary['skipped'] > 0) {
            $parts[] = "{$this->summary['skipped']} skipped";
        }
        if ($this->summary['up_to_date'] > 0) {
            $parts[] = "{$this->summary['up_to_date']} up to date";
        }

        $this->components->info('Summary: '.implode(', ', $parts));

        if (! $isForce && ($this->summary['updated'] > 0 || $this->summary['removed'] > 0)) {
            $this->newLine();
            $this->components->info('Run with --force to apply changes.');
        }

        if ($isForce) {
            $this->components->info('State saved to .aicl-state.json');
        }

        return self::SUCCESS;
    }

    /**
     * Process a single manifest section.
     *
     * @param  array{label: string, entries: list<array<string, string>>}  $section
     */
    protected function processSection(string $key, array $section, bool $isForce, bool $isFresh): void
    {
        $this->components->twoColumnDetail("  <fg=cyan;options=bold>{$section['label']}</>");
        $this->newLine();

        foreach ($section['entries'] as $entry) {
            $strategy = $entry['strategy'];
            match ($strategy) {
                'overwrite' => $this->handleOverwrite($key, $entry, $isForce, $isFresh),
                'ensure_absent' => $this->handleEnsureAbsent($key, $entry, $isForce),
                'ensure_present' => $this->handleEnsurePresent($key, $entry, $isForce),
                default => $this->components->warn("Unknown strategy: {$strategy}"),
            };
        }

        $this->newLine();
    }

    /**
     * Handle 'overwrite' strategy: replace target with source from package stubs.
     *
     * @param  array{strategy: string, target: string, source: string}  $entry
     */
    protected function handleOverwrite(string $sectionKey, array $entry, bool $isForce, bool $isFresh): void
    {
        $target = $entry['target'];
        $sourcePath = $this->packageRoot.'/'.$entry['source'];
        $targetPath = $this->projectRoot.'/'.$target;

        if (! $this->files->exists($sourcePath)) {
            $this->renderLine($target, '<fg=red>ERROR</> source missing', 'error');

            return;
        }

        $sourceContent = $this->files->get($sourcePath);
        $sourceHash = hash('sha256', $sourceContent);

        if (! $this->files->exists($targetPath)) {
            // Target doesn't exist — needs creation
            if ($isForce) {
                $this->files->ensureDirectoryExists(dirname($targetPath));
                $this->files->put($targetPath, $sourceContent);
                $this->updateStateHash($sectionKey, $target, $sourceHash);
                $this->renderLine($target, '<fg=green>created</>');
            } else {
                $this->renderLine($target, '<fg=yellow>would create</>');
            }
            $this->summary['updated']++;

            return;
        }

        $currentContent = $this->files->get($targetPath);
        $currentHash = hash('sha256', $currentContent);

        // Already matches source — up to date
        if ($currentHash === $sourceHash) {
            $this->renderLine($target, '<fg=green>up to date</>');
            $this->summary['up_to_date']++;

            return;
        }

        // File differs — check if user modified it
        $stateHash = $this->getStateHash($sectionKey, $target);
        $userModified = $stateHash !== null && $stateHash !== $currentHash;

        if ($this->option('diff')) {
            $this->showDiff($targetPath, $sourceContent);
        }

        if ($isForce) {
            $this->files->put($targetPath, $sourceContent);
            $this->updateStateHash($sectionKey, $target, $sourceHash);

            if ($userModified) {
                $this->renderLine($target, '<fg=yellow;options=bold>MODIFIED by user — overwriting</>');
            } else {
                $this->renderLine($target, '<fg=green>updated</>');
            }
        } else {
            $label = $userModified
                ? '<fg=yellow>would overwrite</> <fg=gray>(user-modified)</>'
                : '<fg=yellow>would overwrite</>';
            $this->renderLine($target, $label);
        }

        $this->summary['updated']++;
    }

    /**
     * Handle 'ensure_absent' strategy: delete target if it exists.
     *
     * @param  array{strategy: string, target: string, reason: string}  $entry
     */
    protected function handleEnsureAbsent(string $sectionKey, array $entry, bool $isForce): void
    {
        $target = $entry['target'];
        $reason = $entry['reason'];
        $targetPath = $this->projectRoot.'/'.$target;

        $isDirectory = str_ends_with($target, '/');

        if ($isDirectory) {
            $dirPath = rtrim($targetPath, '/');
            if (! $this->files->isDirectory($dirPath)) {
                return; // Already absent — don't even mention it
            }

            $fileCount = count($this->files->allFiles($dirPath));

            if ($isForce) {
                $this->files->deleteDirectory($dirPath);
                $this->updateStateRemoved($sectionKey, $target);
                $this->renderLine($target, "<fg=red>removed</> ({$fileCount} files) — {$reason}");
            } else {
                $this->renderLine($target, "<fg=red>would remove</> ({$fileCount} files) — {$reason}");
            }

            $this->summary['removed'] += $fileCount;
        } else {
            if (! $this->files->exists($targetPath)) {
                return; // Already absent
            }

            if ($isForce) {
                $this->files->delete($targetPath);
                $this->updateStateRemoved($sectionKey, $target);
                $this->renderLine($target, "<fg=red>removed</> — {$reason}");
            } else {
                $this->renderLine($target, "<fg=red>would remove</> — {$reason}");
            }

            $this->summary['removed']++;
        }
    }

    /**
     * Handle 'ensure_present' strategy: copy source if target is missing.
     *
     * @param  array{strategy: string, target: string, source: string}  $entry
     */
    protected function handleEnsurePresent(string $sectionKey, array $entry, bool $isForce): void
    {
        $target = $entry['target'];
        $sourcePath = $this->packageRoot.'/'.$entry['source'];
        $targetPath = $this->projectRoot.'/'.$target;

        if ($this->files->exists($targetPath)) {
            $this->renderLine($target, '<fg=gray>already present, not overwriting</>');
            $this->summary['skipped']++;

            return;
        }

        if (! $this->files->exists($sourcePath)) {
            $this->renderLine($target, '<fg=red>ERROR</> source missing');

            return;
        }

        if ($isForce) {
            $sourceContent = $this->files->get($sourcePath);
            $this->files->ensureDirectoryExists(dirname($targetPath));
            $this->files->put($targetPath, $sourceContent);
            $this->updateStateHash($sectionKey, $target, hash('sha256', $sourceContent));
            $this->renderLine($target, '<fg=green>created</>');
        } else {
            $this->renderLine($target, '<fg=yellow>would create</>');
        }

        $this->summary['updated']++;
    }

    /**
     * Render a formatted output line with aligned dots.
     */
    protected function renderLine(string $target, string $status, string $style = 'info'): void
    {
        $this->components->twoColumnDetail("    {$target}", $status);
    }

    /**
     * Show a simple diff between current file and new content.
     */
    protected function showDiff(string $targetPath, string $newContent): void
    {
        $current = $this->files->get($targetPath);
        $currentLines = explode("\n", $current);
        $newLines = explode("\n", $newContent);

        $maxLines = max(count($currentLines), count($newLines));
        $diffFound = false;

        for ($i = 0; $i < $maxLines; $i++) {
            $oldLine = $currentLines[$i] ?? '';
            $newLine = $newLines[$i] ?? '';

            if ($oldLine !== $newLine) {
                if (! $diffFound) {
                    $this->line('      <fg=gray>--- diff ---</>');
                    $diffFound = true;
                }
                if ($oldLine !== '') {
                    $this->line("      <fg=red>- {$oldLine}</>");
                }
                if ($newLine !== '') {
                    $this->line("      <fg=green>+ {$newLine}</>");
                }
            }
        }

        if ($diffFound) {
            $this->line('      <fg=gray>--- end diff ---</>');
        }
    }

    /**
     * Resolve the package root directory, handling both path-repo and vendor installs.
     */
    protected function resolvePackageRoot(): string
    {
        // Path repository (development): packages/aicl/
        $pathRepo = base_path('packages/aicl');
        if (is_dir($pathRepo)) {
            return $pathRepo;
        }

        // Vendor install (production): vendor/aicl/aicl/
        $vendorPath = base_path('vendor/aicl/aicl');
        if (is_dir($vendorPath)) {
            return $vendorPath;
        }

        // Fallback: use the directory containing this file's package
        return dirname(__DIR__, 3);
    }

    /**
     * Load the upgrade manifest from the package.
     *
     * @return array<string, mixed>|null
     */
    protected function loadManifest(): ?array
    {
        $manifestPath = $this->packageRoot.'/config/upgrade-manifest.php';

        if (! file_exists($manifestPath)) {
            $this->components->error('Upgrade manifest not found at: '.$manifestPath);

            return null;
        }

        return require $manifestPath;
    }

    /**
     * Load the project state file, or return an empty state.
     *
     * @return array<string, mixed>
     */
    protected function loadState(): array
    {
        if ($this->option('fresh')) {
            return [];
        }

        $statePath = $this->projectRoot.'/.aicl-state.json';

        if (! file_exists($statePath)) {
            return [];
        }

        $content = file_get_contents($statePath);
        if ($content === false) {
            return [];
        }

        $decoded = json_decode($content, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Write the state file to the project root.
     */
    protected function writeState(): void
    {
        $statePath = $this->projectRoot.'/.aicl-state.json';
        $json = json_encode($this->state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        $this->files->put($statePath, $json."\n");
    }

    /**
     * Get the stored hash for a file from the state.
     */
    protected function getStateHash(string $section, string $target): ?string
    {
        return $this->state['applied'][$section]['files'][$target] ?? null;
    }

    /**
     * Update the stored hash for a file in the state.
     */
    protected function updateStateHash(string $section, string $target, string $hash): void
    {
        if (! isset($this->state['applied'][$section])) {
            $this->state['applied'][$section] = ['files' => [], 'removed' => []];
        }

        $this->state['applied'][$section]['files'][$target] = $hash;
    }

    /**
     * Record a removed file/directory in the state.
     */
    protected function updateStateRemoved(string $section, string $target): void
    {
        if (! isset($this->state['applied'][$section])) {
            $this->state['applied'][$section] = ['files' => [], 'removed' => []];
        }

        if (! isset($this->state['applied'][$section]['removed'])) {
            $this->state['applied'][$section]['removed'] = [];
        }

        if (! in_array($target, $this->state['applied'][$section]['removed'])) {
            $this->state['applied'][$section]['removed'][] = $target;
        }
    }

    /**
     * Build a state file from the current manifest (for initial installs).
     *
     * @return array<string, mixed>
     */
    public static function buildInitialState(string $packageRoot, string $projectRoot, string $version): array
    {
        $manifestPath = $packageRoot.'/config/upgrade-manifest.php';

        if (! file_exists($manifestPath)) {
            return [
                'package_version' => $version,
                'last_upgraded' => now()->toIso8601String(),
                'applied' => [],
            ];
        }

        $manifest = require $manifestPath;
        $applied = [];

        foreach ($manifest['sections'] as $sectionKey => $section) {
            $sectionState = ['files' => [], 'removed' => []];

            foreach ($section['entries'] as $entry) {
                $target = $entry['target'];
                $targetPath = $projectRoot.'/'.$target;

                if ($entry['strategy'] === 'overwrite' || $entry['strategy'] === 'ensure_present') {
                    if (file_exists($targetPath)) {
                        $content = file_get_contents($targetPath);
                        if ($content !== false) {
                            $sectionState['files'][$target] = hash('sha256', $content);
                        }
                    }
                }
            }

            if (! empty($sectionState['files'])) {
                $applied[$sectionKey] = $sectionState;
            }
        }

        return [
            'package_version' => $version,
            'last_upgraded' => now()->toIso8601String(),
            'applied' => $applied,
        ];
    }
}
