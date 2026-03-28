<?php

declare(strict_types=1);

namespace Aicl\Console\Commands;

use Aicl\Search\SearchDocumentBuilder;
use Aicl\Search\SearchIndexingService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;

/**
 * Rebuilds the global Elasticsearch search index from database records.
 *
 * Supports per-entity filtering, fresh index creation with zero-downtime
 * alias swapping, and chunked bulk indexing. Wraps bulk operations in
 * Model::withoutEvents() to prevent entity event notification storms.
 */
class SearchReindexCommand extends Command
{
    protected $signature = 'search:reindex
        {--entity= : Only reindex a specific entity class (e.g., App\\Models\\User)}
        {--fresh : Drop and recreate the index before reindexing}';

    protected $description = 'Rebuild the global search index from database records.';

    /** @codeCoverageIgnore Reason: external-service -- Requires Elasticsearch connection */
    public function handle(): int
    {
        if (! config('aicl.search.enabled', false)) {
            $this->components->warn('Global search is disabled.');

            return self::SUCCESS;
        }

        $entityConfigs = config('aicl.search.entities', []);

        if (empty($entityConfigs)) {
            $this->components->warn('No entities configured for search indexing.');

            return self::SUCCESS;
        }

        $indexingService = app(SearchIndexingService::class);

        $entityFilter = $this->option('entity');
        if ($entityFilter !== null) {
            $entityConfigs = array_filter(
                $entityConfigs,
                fn (mixed $config, string $class): bool => $class === $entityFilter || class_basename($class) === $entityFilter,
                ARRAY_FILTER_USE_BOTH,
            );

            if (empty($entityConfigs)) {
                $this->components->error("Entity '{$entityFilter}' is not configured for search indexing.");

                return self::FAILURE;
            }
        }

        if ($this->option('fresh')) {
            $this->freshIndex($indexingService);
        } else {
            $indexingService->ensureIndex();
        }

        $documentBuilder = new SearchDocumentBuilder;
        $totalIndexed = 0;

        foreach ($entityConfigs as $modelClass => $config) {
            if (! class_exists($modelClass)) {
                $this->components->warn("Class {$modelClass} does not exist, skipping.");

                continue;
            }

            $this->components->info('Indexing '.class_basename($modelClass).'...');

            $query = $modelClass::query();

            // Exclude soft-deleted records
            if (method_exists(new $modelClass, 'trashed')) {
                // @codeCoverageIgnoreStart — Artisan command
                $query->withoutTrashed();
                // @codeCoverageIgnoreEnd
            }

            $count = 0;
            // Suppress entity events during bulk reindex to prevent notification storm
            Model::withoutEvents(fn () => $query->chunk(200, function ($models) use ($indexingService, $config, $documentBuilder, &$count): void {
                // @codeCoverageIgnoreStart — Artisan command
                $documents = [];

                foreach ($models as $model) {
                    $documents[] = [
                        'id' => $documentBuilder->documentId($model),
                        'body' => $documentBuilder->build($model, $config),
                    ];
                }

                if (! empty($documents)) {
                    $indexingService->bulkIndex($documents);
                    $count += count($documents);
                    // @codeCoverageIgnoreEnd
                }
            }));

            $this->components->info("  Indexed {$count} records.");
            $totalIndexed += $count;
        }

        $this->components->info("Reindex complete. {$totalIndexed} total documents indexed.");

        return self::SUCCESS;
    }

    protected function freshIndex(SearchIndexingService $indexingService): void
    {
        $alias = $indexingService->getIndexAlias();

        $this->components->info('Creating fresh index...');

        // Determine next version
        $v1 = $alias.'_v1';
        $v2 = $alias.'_v2';

        if ($indexingService->indexExists($v2)) {
            $indexingService->deleteIndex($v2);
        }

        if ($indexingService->indexExists($v1)) {
            // Create v2, will swap alias after indexing
            $indexingService->createIndex($v2);
            $indexingService->swapAlias($alias, $v1, $v2);
            $indexingService->deleteIndex($v1);
        } else {
            $indexingService->createIndex($v1);

            $indexingService->swapAlias($alias, '', $v1);
        }
    }
}
