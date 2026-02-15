<?php

namespace Aicl\Observers;

use Aicl\Jobs\RedistillJob;
use Aicl\Models\DistilledLesson;
use Aicl\Models\RlmFailure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * Observer that triggers redistillation when new RlmFailure records are created.
 *
 * When a new failure is created, checks if its failure_code, category, or root_cause
 * clusters with existing failures that already have distilled lessons. If so, dispatches
 * a RedistillJob for the affected cluster. If not, logs that a new lesson may be needed.
 */
class RlmFailureDistillObserver extends BaseObserver
{
    public function created(Model $model): void
    {
        /** @var RlmFailure $model */
        if (! $model->is_active) {
            return;
        }

        $clusterCodes = $this->findClusterCodes($model);

        if ($clusterCodes->isNotEmpty()) {
            // Include the new failure's code in the cluster for redistillation
            $allCodes = $clusterCodes->push($model->failure_code)->unique()->values()->all();

            RedistillJob::dispatch($allCodes);

            Log::info('RlmFailureDistillObserver: Dispatched RedistillJob for cluster.', [
                'new_failure' => $model->failure_code,
                'cluster_codes' => $allCodes,
            ]);
        } else {
            Log::info('RlmFailureDistillObserver: New failure does not cluster with existing lessons. A new lesson may be needed.', [
                'failure_code' => $model->failure_code,
                'category' => $model->category->value,
            ]);
        }
    }

    /**
     * Find failure codes from existing distilled lessons that cluster with the new failure.
     *
     * Checks by: matching source_failure_codes that share the same category,
     * or matching failures with similar root_cause in the same category.
     *
     * @return \Illuminate\Support\Collection<int, string>
     */
    private function findClusterCodes(RlmFailure $newFailure): \Illuminate\Support\Collection
    {
        $categoryValue = $newFailure->category->value;

        // Find existing failures in the same category that are referenced by distilled lessons
        $relatedFailures = RlmFailure::query()
            ->where('is_active', true)
            ->where('id', '!=', $newFailure->id)
            ->where('category', $categoryValue)
            ->when($newFailure->subcategory, function ($query) use ($newFailure) {
                $query->where('subcategory', $newFailure->subcategory);
            })
            ->pluck('failure_code');

        if ($relatedFailures->isEmpty()) {
            return collect();
        }

        // Check which of these failures are already covered by distilled lessons
        $coveredCodes = DistilledLesson::query()
            ->where('is_active', true)
            ->get()
            ->flatMap(function (DistilledLesson $lesson) {
                return $lesson->source_failure_codes ?? [];
            })
            ->unique();

        $matchingCodes = $relatedFailures->intersect($coveredCodes);

        return $matchingCodes->values();
    }
}
