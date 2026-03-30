<?php

declare(strict_types=1);

namespace Aicl\Jobs;

use Aicl\Search\SearchDocumentBuilder;
use Aicl\Search\SearchIndexingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** Queued job that reindexes all entity documents accessible to a user after permission changes. */
class ReindexPermissionsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 10;

    public function __construct(
        public readonly int|string $userId,
    ) {
        $this->queue = 'search';
    }

    public function handle(SearchIndexingService $indexingService): void
    {
        // @codeCoverageIgnoreStart — Job processing
        $entityConfigs = config('aicl.search.entities', []);
        $documentBuilder = new SearchDocumentBuilder;

        foreach ($entityConfigs as $modelClass => $config) {
            if (! class_exists($modelClass)) {
                // @codeCoverageIgnoreEnd
                continue;
            }

            // Find models owned by this user
            // @codeCoverageIgnoreStart — Job processing
            $query = $modelClass::query();

            /** @var Model $model */
            $model = new $modelClass;

            $schema = $model->getConnection()->getSchemaBuilder();
            $table = $model->getTable();

            if (! $schema->hasColumn($table, 'owner_id') && ! $schema->hasColumn($table, 'user_id')) {
                // @codeCoverageIgnoreEnd
                continue;
            }

            // @codeCoverageIgnoreStart — Job processing
            $ownerColumn = $schema->hasColumn($table, 'owner_id') ? 'owner_id' : 'user_id';
            $query->where($ownerColumn, $this->userId);
            // @codeCoverageIgnoreEnd

            // @codeCoverageIgnoreStart — Job processing
            $query->chunk(100, function ($models) use ($indexingService, $config, $documentBuilder): void {
                $documents = [];

                foreach ($models as $model) {
                    $documents[] = [
                        'id' => $documentBuilder->documentId($model),
                        'body' => $documentBuilder->build($model, $config),
                    ];
                }

                if (! empty($documents)) {
                    $indexingService->bulkIndex($documents);
                }
            });
            // @codeCoverageIgnoreEnd
        }
    }
}
