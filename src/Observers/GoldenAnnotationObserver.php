<?php

namespace Aicl\Observers;

use Aicl\Models\GoldenAnnotation;
use Illuminate\Database\Eloquent\Model;

class GoldenAnnotationObserver extends BaseObserver
{
    public function created(Model $model): void
    {
        /** @var GoldenAnnotation $model */
        $model->dispatchEmbeddingJob();
    }

    public function updated(Model $model): void
    {
        /** @var GoldenAnnotation $model */
        $model->dispatchEmbeddingJob();
    }
}
