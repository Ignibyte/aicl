<?php

declare(strict_types=1);

namespace Aicl\Health\Checks;

use Aicl\Health\Contracts\ServiceHealthCheck;
use Aicl\Health\ServiceCheckResult;
use Aicl\Horizon\Contracts\JobRepository;
use Aicl\Horizon\Contracts\SupervisorRepository;
use Illuminate\Redis\Connections\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Throwable;

/**
 * @codeCoverageIgnore External service health check
 */
class QueueCheck implements ServiceHealthCheck
{
    public function check(): ServiceCheckResult
    {
        try {
            $failedThreshold = (int) config('aicl.health.failed_jobs_threshold', 10);

            // Use Horizon data when available, fall back to direct Redis
            $useHorizon = config('aicl.features.horizon', true) && app()->bound(JobRepository::class);
            $details = $useHorizon ? $this->getHorizonDetails() : $this->getDirectRedisDetails();

            // Check failed jobs count
            $failedCount = $this->getFailedCount($useHorizon);

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

    /**
     * Get the count of failed jobs using Horizon or direct DB fallback.
     */
    private function getFailedCount(bool $useHorizon): int
    {
        try {
            if ($useHorizon) {
                return app(JobRepository::class)->countFailed();
            }

            $result = DB::selectOne('SELECT count(*) as count FROM failed_jobs');

            return (int) ($result->count ?? 0);
        } catch (Throwable) {
            // failed_jobs table may not exist
            return 0;
        }
    }

    /**
     * Get queue details from Horizon repositories.
     *
     * @return array<string, string>
     */
    protected function getHorizonDetails(): array
    {
        $details = [];

        $details['Pending'] = (string) app(JobRepository::class)->countPending();
        $details['Completed (recent)'] = (string) app(JobRepository::class)->countCompleted();

        if (app()->bound(SupervisorRepository::class)) {
            $supervisors = app(SupervisorRepository::class)->all();
            $details['Supervisors'] = (string) count($supervisors);

            $totalProcesses = 0;
            foreach ($supervisors as $supervisor) {
                foreach ((array) ($supervisor->processes ?? []) as $count) {
                    $totalProcesses += (int) $count;
                }
            }
            $details['Workers'] = (string) $totalProcesses;
        }

        return $details;
    }

    /**
     * Get queue details directly from Redis (fallback when Horizon is disabled).
     *
     * @return array<string, string>
     */
    protected function getDirectRedisDetails(): array
    {
        $queues = config('aicl.health.queues', ['default', 'notifications', 'high', 'low']);
        $details = [];

        /** @var Connection $connection */
        $connection = Redis::connection();

        foreach ($queues as $queue) {
            $prefix = config('database.redis.options.prefix', '');
            $key = $prefix.'queues:'.$queue;
            $pending = $connection->llen($key);
            $details["Queue: {$queue}"] = "{$pending} pending";
        }

        return $details;
    }

    public function order(): int
    {
        return 50;
    }
}
