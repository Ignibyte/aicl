<?php

declare(strict_types=1);

namespace Aicl\Horizon\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

/**
 * BatchesTable.
 */
class BatchesTable extends Component
{
    /** @codeCoverageIgnore Reason: horizon-process -- Requires job_batches table with real Horizon batch data */
    public function render(): View
    {
        $batches = collect();

        try {
            $batches = DB::table('job_batches')
                ->orderByDesc('created_at')
                ->limit(50)
                ->get()
                ->map(function ($batch) {
                    $batch->progress = $batch->total_jobs > 0
                        ? round((($batch->total_jobs - $batch->pending_jobs) / $batch->total_jobs) * 100)
                        : 0;

                    $batch->created_at_formatted = $batch->created_at
                        ? now()->createFromTimestamp($batch->created_at)->diffForHumans()
                        : null;

                    $batch->finished_at_formatted = $batch->finished_at
                        ? now()->createFromTimestamp($batch->finished_at)->diffForHumans()
                        : null;

                    return $batch;
                });
        } catch (\Throwable) {
            // job_batches table may not exist
        }

        return view('aicl::horizon.livewire.batches-table', [
            'batches' => $batches,
        ]);
    }
}
