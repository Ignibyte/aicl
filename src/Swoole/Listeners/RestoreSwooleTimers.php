<?php

namespace Aicl\Swoole\Listeners;

use Aicl\Swoole\SwooleTimer;
use Laravel\Octane\Events\WorkerStarting;

class RestoreSwooleTimers
{
    /**
     * Restore all persisted timers when an Octane worker starts.
     *
     * Only worker 0 restores timers to prevent duplicate recurring
     * timers across multiple workers. One-shot timers are also limited
     * to worker 0 for consistency.
     */
    public function handle(WorkerStarting $event): void
    {
        // Only restore timers on worker 0 to prevent duplicates across workers.
        // Access Swoole's worker_id via the server instance if available.
        if (app()->bound('Swoole\Http\Server')) {
            $server = app()->make('Swoole\Http\Server');
            if ($server->worker_id !== 0) {
                return;
            }
        }

        SwooleTimer::restore();
    }
}
