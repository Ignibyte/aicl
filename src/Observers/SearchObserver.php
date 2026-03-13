<?php

namespace Aicl\Observers;

use Aicl\Jobs\IndexSearchDocumentJob;
use Illuminate\Database\Eloquent\Model;

class SearchObserver
{
    public function created(Model $model): void
    {
        $this->dispatchIndex($model);
    }

    public function updated(Model $model): void
    {
        $this->dispatchIndex($model);
    }

    public function deleted(Model $model): void
    {
        if (! config('aicl.search.enabled', false)) {
            return;
        }

        $entityConfig = $this->getEntityConfig($model);

        if ($entityConfig === null) {
            return;
        }

        IndexSearchDocumentJob::dispatch(
            get_class($model),
            (string) $model->getKey(),
            'delete',
        );
    }

    public function restored(Model $model): void
    {
        $this->dispatchIndex($model);
    }

    protected function dispatchIndex(Model $model): void
    {
        if (! config('aicl.search.enabled', false)) {
            return;
        }

        // Skip soft-deleted models
        if (method_exists($model, 'trashed') && $model->trashed()) {
            return;
        }

        $entityConfig = $this->getEntityConfig($model);

        if ($entityConfig === null) {
            return;
        }

        IndexSearchDocumentJob::dispatch(
            get_class($model),
            (string) $model->getKey(),
            'index',
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function getEntityConfig(Model $model): ?array
    {
        $configs = config('aicl.search.entities', []);

        return $configs[get_class($model)] ?? null;
    }
}
