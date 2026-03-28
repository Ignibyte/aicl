<?php

declare(strict_types=1);

namespace Aicl\Jobs;

use Aicl\Health\HealthCheckRegistry;
use Aicl\Health\ServiceStatus;
use Aicl\Swoole\Cache\ServiceHealthCacheManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * @codeCoverageIgnore Job processing
 */
class RefreshHealthChecksJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public function handle(HealthCheckRegistry $registry): void
    {
        $results = $registry->runAll();

        // Store results in Redis cache for non-Swoole consumers (e.g., API endpoints)
        Cache::put('aicl:health_check_results', serialize($results), now()->addMinutes(10));

        // Update SwooleCache per-service availability
        foreach ($results as $result) {
            ServiceHealthCacheManager::storeAvailability(
                $result->name,
                $result->status === ServiceStatus::Healthy,
            );
        }

        Log::debug('Health checks refreshed', ['count' => count($results)]);
    }
}
