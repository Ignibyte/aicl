<?php

namespace Aicl\Console\Commands;

use Aicl\Traits\HasSearchableFields;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use ReflectionClass;

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

    public function handle(): int
    {
        $models = $this->discoverSearchableModels();

        if ($models->isEmpty()) {
            $this->components->warn('No models using HasSearchableFields found.');

            return self::SUCCESS;
        }

        $this->components->info('Importing '.count($models).' searchable model(s) into Scout index...');
        $this->newLine();

        $failed = false;

        foreach ($models as $modelClass) {
            $shortName = class_basename($modelClass);

            if ($this->option('flush')) {
                $this->components->task("Flushing {$shortName}", function () use ($modelClass): void {
                    $this->callSilently('scout:flush', ['model' => $modelClass]);
                });
            }

            $result = $this->components->task("Importing {$shortName}", function () use ($modelClass): bool {
                return $this->callSilently('scout:import', ['model' => $modelClass]) === self::SUCCESS;
            });

            if ($result === false) {
                $failed = true;
            }
        }

        $this->newLine();

        if ($failed) {
            $this->components->error('Some imports failed. Check the logs for details.');

            return self::FAILURE;
        }

        $this->components->info('All models imported successfully.');

        return self::SUCCESS;
    }

    /**
     * Discover all Eloquent models that use the HasSearchableFields trait.
     *
     * @return \Illuminate\Support\Collection<int, class-string>
     */
    protected function discoverSearchableModels(): \Illuminate\Support\Collection
    {
        $models = collect();

        $directories = [
            app_path('Models') => 'App\\Models\\',
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
                    $models->push($className);
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

        return array_unique($traits);
    }
}
