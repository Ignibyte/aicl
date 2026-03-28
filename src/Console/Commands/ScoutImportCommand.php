<?php

declare(strict_types=1);

namespace Aicl\Console\Commands;

use Aicl\Traits\HasSearchableFields;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use ReflectionClass;

/**
 * Imports all searchable models into the Scout search index.
 *
 * Discovers models using the HasSearchableFields trait and delegates
 * to scout:import. Wraps bulk operations in Model::withoutEvents()
 * to prevent entity event notification storms.
 */
class ScoutImportCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'aicl:scout-import
        {--flush : Flush all existing index data before importing}';

    /**
     * @var string
     */
    protected $description = 'Import all searchable models into the Scout search index.';

    /** @codeCoverageIgnore Reason: external-service -- Requires live Scout/Elasticsearch for import execution */
    public function handle(): int
    {
        $models = $this->discoverSearchableModels();

        if ($models->isEmpty()) {
            $this->components->warn('No models using HasSearchableFields found.');

            return self::SUCCESS;
        }

        // @codeCoverageIgnoreStart — Artisan command
        $this->components->info('Importing '.count($models).' searchable model(s) into Scout index...');
        $this->newLine();

        $failed = false;

        // Suppress entity events during bulk import to prevent notification storm
        Model::withoutEvents(function () use ($models, &$failed): void {
            foreach ($models as $modelClass) {
                $shortName = class_basename($modelClass);

                if ($this->option('flush')) {
                    $this->components->task("Flushing {$shortName}", function () use ($modelClass): void {
                        $this->callSilently('scout:flush', ['searchable' => [$modelClass]]);
                    });
                }

                $importFailed = false;
                $this->components->task("Importing {$shortName}", function () use ($modelClass, &$importFailed): void {
                    if ($this->callSilently('scout:import', ['searchable' => [$modelClass]]) !== self::SUCCESS) {
                        $importFailed = true;
                    }
                });

                if ($importFailed) {
                    $failed = true;
                }
            }
        });

        $this->newLine();

        if ($failed) {
            $this->components->error('Some imports failed. Check the logs for details.');

            return self::FAILURE;
        }

        $this->components->info('All models imported successfully.');

        return self::SUCCESS;
        // @codeCoverageIgnoreEnd
    }

    /**
     * Discover all Eloquent models that use the HasSearchableFields trait.
     *
     * @return Collection<int, class-string>
     */
    protected function discoverSearchableModels(): Collection
    {
        $models = collect();

        $directories = [
            app_path('Models') => 'App\\Models\\',
            dirname(__DIR__, 2).'/Models' => 'Aicl\\Models\\',
        ];

        foreach ($directories as $directory => $namespace) {
            if (! File::isDirectory($directory)) {
                continue;
            }

            foreach (File::allFiles($directory) as $file) {
                $className = $namespace.str_replace(
                    ['/', '.php'],
                    ['\\', ''],
                    $file->getRelativePathname()
                );

                if (! class_exists($className)) {
                    continue;
                }

                $reflection = new ReflectionClass($className);

                if ($reflection->isAbstract() || $reflection->isInterface()) {
                    continue;
                }

                if (in_array(HasSearchableFields::class, $this->getTraitsRecursive($className), true)) {
                    // @codeCoverageIgnoreStart — Artisan command
                    $models->push($className);
                    // @codeCoverageIgnoreEnd
                }
            }
        }

        return $models;
    }

    /**
     * Get all traits used by a class, including traits used by parent classes and other traits.
     *
     * @return array<int, class-string>
     */
    protected function getTraitsRecursive(string $className): array
    {
        $traits = [];

        do {
            $traits = array_merge($traits, class_uses($className) ?: []);
            foreach (class_uses($className) ?: [] as $trait) {
                $traits = array_merge($traits, class_uses($trait) ?: []);
            }
        } while ($className = get_parent_class($className));

        return array_values(array_unique($traits));
    }
}
