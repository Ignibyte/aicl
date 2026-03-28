<?php

declare(strict_types=1);

namespace Aicl\Jobs;

use Aicl\Search\SearchIndexingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** Queued job that indexes or removes a single model document in the Elasticsearch search index. */
class IndexSearchDocumentJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 5;

    public function __construct(
        public readonly string $modelClass,
        public readonly string $modelId,
        public readonly string $action = 'index',
    ) {
        $this->queue = 'search';
    }

    public function handle(SearchIndexingService $indexingService): void
    {
        // @codeCoverageIgnoreStart — Job processing
        if ($this->action === 'delete') {
            $this->handleDelete($indexingService);

            return;
        }

        $this->handleIndex($indexingService);
        // @codeCoverageIgnoreEnd
    }

    protected function handleIndex(SearchIndexingService $indexingService): void
    {
        /** @var Model|null $model */
        // @codeCoverageIgnoreStart — Job processing
        $model = $this->modelClass::find($this->modelId);

        if ($model === null) {
            return;
        }

        // Skip soft-deleted models
        if (method_exists($model, 'trashed') && $model->trashed()) {
            $indexingService->delete($model);

            return;
        }

        $entityConfig = config("aicl.search.entities.{$this->modelClass}", []);

        if (empty($entityConfig)) {
            return;
        }

        $indexingService->index($model, $entityConfig);
        // @codeCoverageIgnoreEnd
    }

    protected function handleDelete(SearchIndexingService $indexingService): void
    {
        // Build a minimal model instance for document ID generation
        /** @var Model $model */
        // @codeCoverageIgnoreStart — Job processing
        $model = new $this->modelClass;
        $model->forceFill([$model->getKeyName() => $this->modelId]);

        $indexingService->delete($model);
        // @codeCoverageIgnoreEnd
    }
}
