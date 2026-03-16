<?php

namespace Aicl\Swoole\Listeners;

use Aicl\Swoole\SwooleCache;
use Laravel\Octane\Events\WorkerStarting;

/**
 * Octane WorkerStarting listener that warms all registered SwooleCache tables.
 *
 * @see SwooleCache::registerWarm()  Registers warm callbacks
 */
class WarmSwooleCaches
{
    /**
     * Warm all registered SwooleCache tables when an Octane worker starts.
     *
     * Swoole Tables are shared memory — the first worker to warm writes
     * the data, subsequent workers see it immediately. Warm is idempotent
     * (overwrites with same data).
     */
    public function handle(WorkerStarting $event): void
    {
        foreach (SwooleCache::warmCallbacks() as $table => $loaders) {
            foreach ($loaders as $loader) {
                SwooleCache::warm($table, $loader);
            }
        }
    }
}
