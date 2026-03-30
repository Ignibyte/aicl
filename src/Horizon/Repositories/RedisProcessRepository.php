<?php

declare(strict_types=1);

namespace Aicl\Horizon\Repositories;

use Aicl\Horizon\Contracts\ProcessRepository;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Redis\Factory;
use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Illuminate\Redis\Connections\Connection;

/**
 * @codeCoverageIgnore Horizon process management
 */
class RedisProcessRepository implements ProcessRepository
{
    /**
     * The Redis connection instance.
     *
     * @var Factory
     */
    public $redis;

    /**
     * Create a new repository instance.
     */
    public function __construct(RedisFactory $redis)
    {
        $this->redis = $redis;
    }

    /**
     * Get all of the orphan process IDs and the times they were observed.
     *
     * @param string $master
     *
     * @return array<string, string>
     */
    public function allOrphans($master)
    {
        return $this->connection()->hgetall(
            "{$master}:orphans"
        );
    }

    /**
     * Record the given process IDs as orphaned.
     *
     * @param string             $master
     * @param array<int, string> $processIds
     */
    public function orphaned($master, array $processIds)
    {
        $time = CarbonImmutable::now()->getTimestamp();

        $shouldRemove = array_diff($this->connection()->hkeys(
            $key = "{$master}:orphans"
        ), $processIds);

        if ($shouldRemove !== []) {
            $this->connection()->hdel($key, ...$shouldRemove);
        }

        $this->connection()->pipeline(function ($pipe) use ($key, $time, $processIds) {
            foreach ($processIds as $processId) {
                $pipe->hsetnx($key, $processId, $time);
            }
        });
    }

    /**
     * Get the process IDs orphaned for at least the given number of seconds.
     *
     * @param string $master
     * @param int    $seconds
     *
     * @return array<int, string>
     */
    public function orphanedFor($master, $seconds)
    {
        $expiresAt = CarbonImmutable::now()->getTimestamp() - $seconds;

        return collect($this->allOrphans($master))
            ->filter(fn ($recordedAt, $_) => $expiresAt > $recordedAt)
            ->keys()
            ->all();
    }

    /**
     * Remove the given process IDs from the orphan list.
     *
     * @param string             $master
     * @param array<int, string> $processIds
     */
    public function forgetOrphans($master, array $processIds)
    {
        $this->connection()->hdel(
            "{$master}:orphans", ...$processIds
        );
    }

    /**
     * Get the Redis connection instance.
     *
     * @return Connection
     */
    protected function connection()
    {
        return $this->redis->connection('horizon');
    }
}
