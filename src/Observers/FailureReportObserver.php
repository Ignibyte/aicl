<?php

namespace Aicl\Observers;

use Aicl\Jobs\CheckPromotionCandidatesJob;
use Aicl\Models\FailureReport;
use Aicl\Models\RlmFailure;
use Aicl\Notifications\FailureRegressionNotification;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Observer for FailureReport entity lifecycle events.
 *
 * When a new report is created, this observer:
 * 1. Increments the parent failure's report_count
 * 2. Checks for unique project_hash → increments project_count
 * 3. If resolved → increments resolution_count and recomputes resolution_rate
 * 4. Updates last_seen_at timestamp
 * 5. If promotion criteria met → dispatches CheckPromotionCandidatesJob
 * 6. If parent failure was previously fixed (scaffolding_fixed) → sends regression notification
 */
class FailureReportObserver extends BaseObserver
{
    public function created(Model $model): void
    {
        /** @var FailureReport $model */
        activity()
            ->performedOn($model)
            ->log("Failure report for \"{$model->entity_name}\" was created");

        $failure = $model->failure;
        if (! $failure) {
            return;
        }

        // 1. Increment report_count
        $failure->increment('report_count');

        // 2. Check unique project_hash → increment project_count
        if ($model->project_hash) {
            $uniqueProjects = FailureReport::where('rlm_failure_id', $failure->id)
                ->distinct('project_hash')
                ->count('project_hash');

            if ($uniqueProjects > $failure->project_count) {
                $failure->update(['project_count' => $uniqueProjects]);
            }
        }

        // 3. If resolved → increment resolution_count and recompute resolution_rate
        if ($model->resolved) {
            $failure->increment('resolution_count');
            $failure->refresh();
            $failure->update([
                'resolution_rate' => $failure->computed_resolution_rate,
            ]);
        }

        // 4. Update last_seen_at
        $failure->update(['last_seen_at' => now()]);

        // 5. Check promotion criteria → dispatch job
        if ($failure->report_count >= 3 && $failure->project_count >= 2 && ! $failure->promoted_to_base) {
            CheckPromotionCandidatesJob::dispatch($failure);
        }

        // 6. Regression detection: if parent was scaffolding_fixed, this is a regression
        if ($failure->scaffolding_fixed) {
            $this->sendRegressionNotification($failure, $model);
        }
    }

    public function deleted(Model $model): void
    {
        /** @var FailureReport $model */
        activity()
            ->performedOn($model)
            ->log("Failure report for \"{$model->entity_name}\" was deleted");
    }

    protected function sendRegressionNotification(RlmFailure $failure, FailureReport $report): void
    {
        $recipient = $failure->owner
            ?? User::role('admin')->first();

        if ($recipient) {
            $recipient->notify(new FailureRegressionNotification($failure, $report));
        }
    }
}
