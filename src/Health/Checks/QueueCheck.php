<?php

namespace Aicl\Health\Checks;

use Aicl\Health\Contracts\ServiceHealthCheck;
use Aicl\Health\ServiceCheckResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Throwable;

class QueueCheck implements ServiceHealthCheck
{
    public function check(): ServiceCheckResult
    {
        try {
            $queues = config('aicl.health.queues', ['default', 'notifications', 'high', 'low']);
            $failedThreshold = (int) config('aicl.health.failed_jobs_threshold', 10);
            $details = [];

            // Check pending jobs per queue via Redis
            /** @var \Illuminate\Redis\Connections\Connection $connection */
            $connection = Redis::connection();

            foreach ($queues as $queue) {
                $prefix = config('database.redis.options.prefix', '');
                $key = $prefix.'queues:'.$queue;
                $pending = $connection->llen($key);
                $details["Queue: {$queue}"] = "{$pending} pending";
            }

            // Check failed jobs count
            $failedCount = 0;

            try {
                $result = DB::selectOne('SELECT count(*) as count FROM failed_jobs');
                $failedCount = (int) ($result->count ?? 0);
            } catch (Throwable) {
                // failed_jobs table may not exist
            }

            $details['Failed Jobs'] = (string) $failedCount;

            if ($failedCount >= $failedThreshold) {
                return ServiceCheckResult::degraded(
                    name: 'Queues',
                    icon: 'heroicon-o-queue-list',
                    details: $details,
                    error: "Failed jobs ({$failedCount}) exceed threshold ({$failedThreshold}).",
                );
            }

            return ServiceCheckResult::healthy(
                name: 'Queues',
                icon: 'heroicon-o-queue-list',
                details: $details,
            );
        } catch (Throwable $e) {
            return ServiceCheckResult::down(
                name: 'Queues',
                icon: 'heroicon-o-queue-list',
                error: $e->getMessage(),
            );
        }
    }

    public function order(): int
    {
        return 50;
    }
}
