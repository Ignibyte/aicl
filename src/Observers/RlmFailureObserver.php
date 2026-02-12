<?php

namespace Aicl\Observers;

use Aicl\Models\RlmFailure;
use Illuminate\Database\Eloquent\Model;

/**
 * Observer for RlmFailure entity lifecycle events.
 */
class RlmFailureObserver extends BaseObserver
{
    public function created(Model $model): void
    {
        /** @var RlmFailure $model */
        activity()
            ->performedOn($model)
            ->log('RlmFailure "'.$model->failure_code.'" was created');

        $model->dispatchEmbeddingJob();
    }

    public function updated(Model $model): void
    {
        /** @var RlmFailure $model */
        $model->dispatchEmbeddingJob();
    }

    public function updating(Model $model): void
    {
        /** @var RlmFailure $model */
        if ($model->isDirty('status')) {
            $oldStatus = $model->getOriginal('status');
            $newStatus = $model->status;

            activity()
                ->performedOn($model)
                ->withProperties([
                    'old_status' => $oldStatus ? (string) $oldStatus : null,
                    'new_status' => (string) $newStatus,
                ])
                ->log('RlmFailure "'.$model->failure_code.'" status changed from '.($oldStatus ? (string) $oldStatus : 'none').' to '.(string) $newStatus);
        }
    }

    public function deleted(Model $model): void
    {
        /** @var RlmFailure $model */
        activity()
            ->performedOn($model)
            ->log('RlmFailure "'.$model->failure_code.'" was deleted');
    }
}
