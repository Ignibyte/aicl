<?php

namespace Aicl\Jobs;

use Aicl\Rlm\DistillationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Queued job that re-distills a specific cluster of failures.
 *
 * Dispatched by RlmFailureDistillObserver when a new failure is created
 * that clusters with existing failures covered by distilled lessons.
 */
class RedistillJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 10;

    /**
     * @param  array<int, string>  $failureCodes  The failure codes forming the cluster to redistill.
     */
    public function __construct(
        public array $failureCodes,
    ) {}

    public function handle(DistillationService $service): void
    {
        if (empty($this->failureCodes)) {
            Log::debug('RedistillJob: No failure codes provided, skipping.');

            return;
        }

        Log::info('RedistillJob: Re-distilling cluster.', [
            'failure_codes' => $this->failureCodes,
        ]);

        $result = $service->distillCluster($this->failureCodes);

        Log::info('RedistillJob: Redistillation complete.', [
            'failure_codes' => $this->failureCodes,
            'clusters' => $result['clusters'],
            'lessons' => $result['lessons'],
            'agents' => $result['agents'],
        ]);
    }
}
