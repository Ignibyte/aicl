<?php

namespace Aicl\Observers;

use Aicl\Models\RlmLesson;
use Illuminate\Database\Eloquent\Model;

/**
 * Observer for RlmLesson entity lifecycle events.
 */
class RlmLessonObserver extends BaseObserver
{
    public function created(Model $model): void
    {
        /** @var RlmLesson $model */
        activity()
            ->performedOn($model)
            ->log("Lesson \"{$model->summary}\" (topic: {$model->topic}) was created");

        $model->dispatchEmbeddingJob();
    }

    public function updated(Model $model): void
    {
        /** @var RlmLesson $model */
        $model->dispatchEmbeddingJob();
    }

    public function deleted(Model $model): void
    {
        /** @var RlmLesson $model */
        activity()
            ->performedOn($model)
            ->log("Lesson \"{$model->summary}\" (topic: {$model->topic}) was deleted");
    }
}
