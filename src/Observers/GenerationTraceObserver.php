<?php

namespace Aicl\Observers;

use Aicl\Models\GenerationTrace;
use Illuminate\Database\Eloquent\Model;

/**
 * Observer for GenerationTrace entity lifecycle events.
 */
class GenerationTraceObserver extends BaseObserver
{
    public function created(Model $model): void
    {
        /** @var GenerationTrace $model */
        activity()
            ->performedOn($model)
            ->log('GenerationTrace "'.$model->entity_name.'" was created');
    }

    public function deleted(Model $model): void
    {
        /** @var GenerationTrace $model */
        activity()
            ->performedOn($model)
            ->log('GenerationTrace "'.$model->entity_name.'" was deleted');
    }
}
