<?php

declare(strict_types=1);

namespace Aicl\Console\Commands;

use Aicl\Services\EntityRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/** Removes all generated files for an AICL entity, inverse of aicl:make-entity. */
class RemoveEntityCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'aicl:remove-entity
        {name : The name of the entity to remove (e.g., Task, Invoice)}
        {--dry-run : Preview what will be deleted without making changes}
        {--force : Skip confirmation prompt}';

    /**
     * @var string
     */
    protected $description = 'Remove all generated files for an AICL entity (inverse of aicl:make-entity).';

    /**
     * Files discovered for deletion.
     *
     * @var array<int, string>
     */
    protected array $filesToDelete = [];

    /**
     * Directories discovered for deletion.
     *
     * @var array<int, string>
     */
    protected array $dirsToDelete = [];

    /**
     * Shared files that need line removals.
     *
     * @var array<string, array<int, string>>
     */
    protected array $sharedFileCleanups = [];

    public function handle(): int
    {
        $name = Str::studly($this->argument('name'));
        $plural = Str::pluralStudly($name);
        $snakePlural = Str::snake($plural);
        $isDryRun = $this->option('dry-run');

        $this->components->info(($isDryRun ? '[DRY RUN] ' : '')."Scanning for entity: {$name}");
        $this->newLine();

        $this->discoverEntityFiles($name, $plural, $snakePlural);
        $this->discoverSharedFileCleanups($name, $plural, $snakePlural);
        $hasTable = Schema::hasTable($snakePlural);

        if (empty($this->filesToDelete) && empty($this->dirsToDelete) && empty($this->sharedFileCleanups) && ! $hasTable) {
            $this->components->warn("No files found for entity '{$name}'. Nothing to remove.");

            return self::SUCCESS;
        }

        $this->displayDiscoveredItems($snakePlural, $hasTable);

        if ($isDryRun) {
            $this->newLine();
            $this->components->info('[DRY RUN] No changes were made. Run without --dry-run to execute.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->components->confirm('Proceed with removal?', false)) {
            $this->components->info('Cancelled. No changes were made.');

            return self::SUCCESS;
        }

        $deleted = $this->executeRemoval();
        $cleaned = $this->executeSharedFileCleanups();
        $dbCleaned = $this->executeDatabaseCleanup($snakePlural);

        $this->newLine();
        $this->components->info("Removed {$deleted} file(s)/director(ies). Cleaned {$cleaned} shared file(s).{$dbCleaned}");

        EntityRegistry::flush();

        return self::SUCCESS;
    }

    /**
     * Discover all entity-specific files and directories.
     */
    protected function discoverEntityFiles(string $name, string $plural, string $snakePlural): void
    {
        $snake = Str::snake($name);

        // Single files — exact matches
        $singleFiles = [
            'Model' => app_path("Models/{$name}.php"),
            'Policy' => app_path("Policies/{$name}Policy.php"),
            'Observer' => app_path("Observers/{$name}Observer.php"),
            'Created Event' => app_path("Events/{$name}Created.php"),
            'Updated Event' => app_path("Events/{$name}Updated.php"),
            'Deleted Event' => app_path("Events/{$name}Deleted.php"),
            'Exporter' => app_path("Filament/Exporters/{$name}Exporter.php"),
            'API Controller' => app_path("Http/Controllers/Api/{$name}Controller.php"),
            'Store Request' => app_path("Http/Requests/Store{$name}Request.php"),
            'Update Request' => app_path("Http/Requests/Update{$name}Request.php"),
            'API Resource' => app_path("Http/Resources/{$name}Resource.php"),
            'Factory' => database_path("factories/{$name}Factory.php"),
            'Seeder' => database_path("seeders/{$name}Seeder.php"),
            'Entity Test' => base_path("tests/Feature/Entities/{$name}Test.php"),
            'API Test' => base_path("tests/Feature/Api/{$name}CrudTest.php"),
            'Resource Test' => base_path("tests/Unit/Filament/Resources/{$name}ResourceTest.php"),
        ];

        foreach ($singleFiles as $file) {
            if (file_exists($file)) {
                $this->filesToDelete[] = $file;
            }
        }

        // Glob-matched files — pattern matches
        $globPatterns = [
            'Enums' => app_path("Enums/{$name}*.php"),
            'Widgets (prefix)' => app_path("Filament/Widgets/{$name}*.php"),
            'Widgets (contains)' => app_path("Filament/Widgets/*{$plural}*.php"),
            'Notifications' => app_path("Notifications/{$name}*.php"),
            'Migration' => database_path("migrations/*_create_{$snakePlural}_table.php"),
        ];

        foreach ($globPatterns as $pattern) {
            foreach (glob($pattern) ?: [] as $file) {
                $this->filesToDelete[] = $file;
            }
        }

        // Abstract state class (sits alongside the States/{Name}/ directory)
        $abstractState = app_path("States/{$name}State.php");
        if (file_exists($abstractState)) {
            $this->filesToDelete[] = $abstractState;
        }

        // Directories
        $dirs = [
            'Filament Resource' => app_path("Filament/Resources/{$plural}"),
            'States' => app_path("States/{$name}"),
            'PDF Views' => resource_path("views/pdf/{$snakePlural}"),
            'Widget Views' => resource_path("views/filament/resources/{$snakePlural}"),
        ];

        foreach ($dirs as $dir) {
            if (is_dir($dir)) {
                $this->dirsToDelete[] = $dir;
            }
        }

        // Also check for single PDF view files (some entities use flat files instead of directories)
        $pdfGlob = resource_path("views/pdf/{$snake}*.blade.php");
        foreach (glob($pdfGlob) ?: [] as $file) {
            if (! in_array($file, $this->filesToDelete)) {
                $this->filesToDelete[] = $file;
            }
        }
        $pdfPluralGlob = resource_path("views/pdf/{$snakePlural}*.blade.php");
        foreach (glob($pdfPluralGlob) ?: [] as $file) {
            if (! in_array($file, $this->filesToDelete)) {
                $this->filesToDelete[] = $file;
            }
        }
    }

    /**
     * Discover lines to remove from shared registration files.
     */
    protected function discoverSharedFileCleanups(string $name, string $plural, string $snakePlural): void
    {
        $this->scanAppServiceProvider($name);
        $this->scanApiRoutes($name, $snakePlural);
        $this->scanChannelsFile($name, $snakePlural);
        $this->scanDatabaseSeeder($name);
    }

    /**
     * Scan AppServiceProvider for entity references.
     */
    protected function scanAppServiceProvider(string $name): void
    {
        $file = app_path('Providers/AppServiceProvider.php');
        if (! file_exists($file)) {
            return;
        }

        $content = file_get_contents($file);
        if ($content === false) {
            return;
        }
        $patterns = [
            "use App\\Models\\{$name};",
            "use App\\Observers\\{$name}Observer;",
            "use App\\Policies\\{$name}Policy;",
            "Gate::policy({$name}::class",
            "{$name}::observe(",
        ];

        $matches = [];
        foreach ($patterns as $pattern) {
            if (str_contains($content, $pattern)) {
                $matches[] = $pattern;
            }
        }

        if (! empty($matches)) {
            $this->sharedFileCleanups[$file] = $matches;
        }
    }

    /**
     * Scan routes/api.php for entity API routes.
     */
    protected function scanApiRoutes(string $name, string $snakePlural): void
    {
        $file = base_path('routes/api.php');
        if (! file_exists($file)) {
            return;
        }

        $content = file_get_contents($file);
        if ($content === false) {
            return;
        }
        $patterns = [
            "use App\\Http\\Controllers\\Api\\{$name}Controller;",
            "'{$snakePlural}'",
            "{$name}Controller::class",
        ];

        $matches = [];
        foreach ($patterns as $pattern) {
            if (str_contains($content, $pattern)) {
                $matches[] = $pattern;
            }
        }

        if (! empty($matches)) {
            $this->sharedFileCleanups[$file] = $matches;
        }
    }

    /**
     * Scan routes/channels.php for entity broadcast channels.
     */
    protected function scanChannelsFile(string $name, string $snakePlural): void
    {
        $file = base_path('routes/channels.php');
        if (! file_exists($file)) {
            return;
        }

        $content = file_get_contents($file);
        if ($content === false) {
            return;
        }
        $patterns = [
            "use App\\Models\\{$name};",
            "'{$snakePlural}.",
        ];

        $matches = [];
        foreach ($patterns as $pattern) {
            if (str_contains($content, $pattern)) {
                $matches[] = $pattern;
            }
        }

        if (! empty($matches)) {
            $this->sharedFileCleanups[$file] = $matches;
        }
    }

    /**
     * Scan DatabaseSeeder for entity seeder references.
     */
    protected function scanDatabaseSeeder(string $name): void
    {
        $file = database_path('seeders/DatabaseSeeder.php');
        if (! file_exists($file)) {
            return;
        }

        $content = file_get_contents($file);
        if ($content === false) {
            return;
        }
        $pattern = "{$name}Seeder::class";

        if (str_contains($content, $pattern)) {
            $this->sharedFileCleanups[$file] = [$pattern];
        }
    }

    /**
     * Display all discovered items to be removed.
     */
    protected function displayDiscoveredItems(string $snakePlural, bool $hasTable): void
    {
        if (! empty($this->filesToDelete)) {
            $this->components->twoColumnDetail('<fg=yellow>Files to delete</>');
            foreach ($this->filesToDelete as $file) {
                $this->components->twoColumnDetail(
                    '  '.str_replace(base_path().'/', '', $file),
                    '<fg=red>DELETE</>'
                );
            }
            $this->newLine();
        }

        if (! empty($this->dirsToDelete)) {
            $this->components->twoColumnDetail('<fg=yellow>Directories to delete</>');
            foreach ($this->dirsToDelete as $dir) {
                $this->components->twoColumnDetail(
                    '  '.str_replace(base_path().'/', '', $dir).'/',
                    '<fg=red>DELETE</>'
                );
            }
            $this->newLine();
        }

        if (! empty($this->sharedFileCleanups)) {
            $this->components->twoColumnDetail('<fg=yellow>Shared files to clean</>');
            foreach ($this->sharedFileCleanups as $file => $patterns) {
                $relativePath = str_replace(base_path().'/', '', $file);
                $this->components->twoColumnDetail("  {$relativePath}", '<fg=cyan>MODIFY</>');
                foreach ($patterns as $pattern) {
                    $this->line("    - Remove: <comment>{$pattern}</comment>");
                }
            }
            $this->newLine();
        }

        if ($hasTable) {
            $this->components->twoColumnDetail('<fg=yellow>Database cleanup</>');
            $rowCount = DB::table($snakePlural)->count();
            $this->components->twoColumnDetail(
                "  Table '{$snakePlural}' ({$rowCount} rows)",
                '<fg=red>DROP</>'
            );
            $this->components->twoColumnDetail(
                '  Migration record',
                '<fg=red>DELETE</>'
            );
            $this->newLine();
        }
    }

    /**
     * Delete discovered files and directories.
     */
    protected function executeRemoval(): int
    {
        $count = 0;

        foreach ($this->filesToDelete as $file) {
            if (file_exists($file)) {
                unlink($file);
                $count++;
                $this->cleanEmptyParentDirectories(dirname($file));
            }
        }

        foreach ($this->dirsToDelete as $dir) {
            if (is_dir($dir)) {
                $this->removeDirectoryRecursive($dir);
                $count++;
                $this->cleanEmptyParentDirectories(dirname($dir));
            }
        }

        return $count;
    }

    /**
     * Remove parent directories if they are empty after file deletion.
     *
     * Only removes directories within app/, database/, tests/, and resources/
     * to avoid accidentally removing important project directories.
     */
    protected function cleanEmptyParentDirectories(string $dir): void
    {
        $safePrefixes = [
            app_path(),
            database_path(),
            base_path('tests'),
            resource_path(),
        ];

        while ($dir && is_dir($dir) && $this->isDirectoryEmpty($dir)) {
            $isSafe = false;
            foreach ($safePrefixes as $prefix) {
                if (str_starts_with($dir, $prefix) && $dir !== $prefix) {
                    $isSafe = true;

                    break;
                }
            }

            if (! $isSafe) {
                break;
            }

            rmdir($dir);
            $dir = dirname($dir);
        }
    }

    /**
     * Check if a directory is empty (no files or subdirectories).
     */
    protected function isDirectoryEmpty(string $dir): bool
    {
        return is_dir($dir) && count(array_diff(scandir($dir), ['.', '..'])) === 0;
    }

    /**
     * Clean shared registration files by removing entity-related lines.
     */
    protected function executeSharedFileCleanups(): int
    {
        $count = 0;

        foreach ($this->sharedFileCleanups as $file => $patterns) {
            if (! file_exists($file)) {
                continue;
            }

            $content = file_get_contents($file);
            if ($content === false) {
                continue;
            }
            $originalContent = $content;

            foreach ($patterns as $pattern) {
                $content = $this->removeLinesContaining($content, $pattern);
            }

            // Clean up consecutive blank lines left behind
            $content = preg_replace("/\n{3,}/", "\n\n", $content) ?? $content;

            if ($content !== $originalContent) {
                file_put_contents($file, $content);
                $count++;
            }
        }

        return $count;
    }

    /**
     * Remove all lines from content that contain the given string.
     */
    protected function removeLinesContaining(string $content, string $needle): string
    {
        $lines = explode("\n", $content);
        $filtered = array_filter($lines, fn (string $line): bool => ! str_contains($line, $needle));

        return implode("\n", $filtered);
    }

    /**
     * Drop the entity table and remove its migration record.
     */
    protected function executeDatabaseCleanup(string $snakePlural): string
    {
        $messages = [];

        if (Schema::hasTable($snakePlural)) {
            Schema::dropIfExists($snakePlural);
            $messages[] = "dropped table '{$snakePlural}'";
        }

        $migrationPattern = "%_create_{$snakePlural}_table";
        $deleted = DB::table('migrations')->where('migration', 'like', $migrationPattern)->delete();
        if ($deleted > 0) {
            $messages[] = "removed {$deleted} migration record(s)";
        }

        if (empty($messages)) {
            return '';
        }

        return ' DB: '.implode(', ', $messages).'.';
    }

    /**
     * Recursively remove a directory and all its contents.
     */
    protected function removeDirectoryRecursive(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $items = array_diff(scandir($dir), ['.', '..']);

        foreach ($items as $item) {
            $path = $dir.'/'.$item;
            if (is_dir($path)) {
                $this->removeDirectoryRecursive($path);
            } else {
                unlink($path);
            }
        }

        rmdir($dir);
    }
}
