<?php

namespace Aicl\Swoole\Listeners;

use Aicl\Swoole\SwooleTimer;
use Laravel\Octane\Events\WorkerStarting;

class RestoreSwooleTimers
{
    /**
     * Restore all persisted timers when Octane worker 0 starts.
     *
     * Only worker 0 restores timers to prevent duplicate recurring
     * timers across multiple workers.
     */
    public function handle(WorkerStarting $event): void
    {
        // Use worker ID from Octane event state (always available),
        // with Swoole server fallback for edge cases.
        $workerId = $event->workerState->workerId
            ?? (app()->bound('Swoole\Http\Server') ? app()->make('Swoole\Http\Server')->worker_id : 0);

        if ($workerId !== 0) {
            return;
        }

        SwooleTimer::restore();
    }
}
