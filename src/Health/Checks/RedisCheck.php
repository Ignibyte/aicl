<?php

declare(strict_types=1);

namespace Aicl\Health\Checks;

use Aicl\Health\Contracts\ServiceHealthCheck;
use Aicl\Health\ServiceCheckResult;
use Illuminate\Redis\Connections\Connection;
use Illuminate\Support\Facades\Redis;
use Throwable;

/**
 * Health check for Redis connectivity and response time.
 *
 * @codeCoverageIgnore External service health check
 */
class RedisCheck implements ServiceHealthCheck
{
    public function check(): ServiceCheckResult
    {
        try {
            /** @var Connection $connection */
            $connection = Redis::connection();
            $connection->ping();

            $info = $connection->info();

            // Redis info() can return nested arrays or flat depending on driver
            $server = $info['Server'] ?? $info;
            $memory = $info['Memory'] ?? $info;
            $clients = $info['Clients'] ?? $info;
            $keyspace = $info['Keyspace'] ?? $info;

            $version = $server['redis_version'] ?? 'Unknown';
            $memoryUsed = $memory['used_memory_human'] ?? 'Unknown';
            $connectedClients = $clients['connected_clients'] ?? 'Unknown';
            $uptimeDays = $server['uptime_in_days'] ?? 'Unknown';

            $totalKeys = $this->countTotalKeys($keyspace);

            $details = [
                'Version' => (string) $version,
                'Memory Used' => (string) $memoryUsed,
                'Connected Clients' => (string) $connectedClients,
                'Total Keys' => (string) $totalKeys,
                'Uptime' => $uptimeDays.' days',
            ];

            // Check memory threshold for degraded status
            $maxMemory = $memory['maxmemory'] ?? 0;
            if ($maxMemory > 0) {
                $usedMemory = $memory['used_memory'] ?? 0;
                $ratio = $usedMemory / $maxMemory;

                if ($ratio > 0.9) {
                    return ServiceCheckResult::degraded(
                        name: 'Redis',
                        icon: 'heroicon-o-server-stack',
                        details: $details,
                        error: 'Memory usage above 90% of maxmemory.',
                    );
                }
            }

            return ServiceCheckResult::healthy(
                name: 'Redis',
                icon: 'heroicon-o-server-stack',
                details: $details,
            );
        } catch (Throwable $e) {
            return ServiceCheckResult::down(
                name: 'Redis',
                icon: 'heroicon-o-server-stack',
                error: $e->getMessage(),
            );
        }
    }

    public function order(): int
    {
        return 30;
    }

    /**
     * Count total keys across all Redis databases from the Keyspace info section.
     *
     * @param  array<string, mixed>  $keyspace
     */
    protected function countTotalKeys(array $keyspace): int
    {
        $total = 0;

        foreach ($keyspace as $key => $value) {
            if (! str_starts_with((string) $key, 'db')) {
                continue;
            }

            if (is_string($value) && preg_match('/keys=(\d+)/', $value, $matches)) {
                $total += (int) $matches[1];
            }
        }

        return $total;
    }
}
