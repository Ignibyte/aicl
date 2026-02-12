<?php

namespace Aicl\Observers;

use Aicl\Models\RlmPattern;
use Illuminate\Database\Eloquent\Model;

/**
 * Observer for RlmPattern entity lifecycle events.
 */
class RlmPatternObserver extends BaseObserver
{
    public function created(Model $model): void
    {
        /** @var RlmPattern $model */
        activity()
            ->performedOn($model)
            ->log('RlmPattern "'.$model->name.'" was created');

        $model->dispatchEmbeddingJob();
    }

    public function updated(Model $model): void
    {
        /** @var RlmPattern $model */
        $model->dispatchEmbeddingJob();
    }

    public function deleted(Model $model): void
    {
        /** @var RlmPattern $model */
        activity()
            ->performedOn($model)
            ->log('RlmPattern "'.$model->name.'" was deleted');
    }
}
