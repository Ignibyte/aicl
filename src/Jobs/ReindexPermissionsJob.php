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
        $entityConfigs = config('aicl.search.entities', []);
        $documentBuilder = new SearchDocumentBuilder;

        foreach ($entityConfigs as $modelClass => $config) {
            if (! class_exists($modelClass)) {
                continue;
            }

            // Find models owned by this user
            $query = $modelClass::query();

            /** @var Model $model */
            $model = new $modelClass;

            if ($model->getConnection()->getSchemaBuilder()->hasColumn($model->getTable(), 'owner_id')) {
                $query->where('owner_id', $this->userId);
            } elseif ($model->getConnection()->getSchemaBuilder()->hasColumn($model->getTable(), 'user_id')) {
                $query->where('user_id', $this->userId);
            } else {
                continue;
            }

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
        }
    }
}
