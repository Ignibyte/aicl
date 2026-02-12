<?php

namespace Aicl\Observers;

use Aicl\Models\PreventionRule;
use Illuminate\Database\Eloquent\Model;

class PreventionRuleObserver extends BaseObserver
{
    public function created(Model $model): void
    {
        /** @var PreventionRule $model */
        activity()
            ->performedOn($model)
            ->log('PreventionRule "'.str($model->rule_text)->limit(50).'" was created');

        $model->dispatchEmbeddingJob();
    }

    public function updated(Model $model): void
    {
        /** @var PreventionRule $model */
        $model->dispatchEmbeddingJob();
    }

    public function deleted(Model $model): void
    {
        /** @var PreventionRule $model */
        activity()
            ->performedOn($model)
            ->log('PreventionRule "'.str($model->rule_text)->limit(50).'" was deleted');
    }
}
