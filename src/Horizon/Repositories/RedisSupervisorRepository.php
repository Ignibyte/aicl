<?php

declare(strict_types=1);

namespace Aicl\Horizon\Repositories;

use Aicl\Horizon\Contracts\SupervisorRepository;
use Aicl\Horizon\Supervisor;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Redis\Factory;
use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Illuminate\Redis\Connections\Connection;
use Illuminate\Support\Arr;
use stdClass;

/**
 * Redis-backed repository for Horizon supervisor state.
 *
 * Stores supervisor heartbeats, process counts, and configuration
 * in Redis sorted sets and hashes via the 'horizon' connection.
 */
class RedisSupervisorRepository implements SupervisorRepository
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
     * Get the names of all the supervisors currently running.
     *
     * @return array<int, string>
     */
    public function names()
    {
        $result = $this->connection()->zrevrangebyscore('supervisors', '+inf',
            (string) CarbonImmutable::now()->subSeconds(29)->getTimestamp()
        );

        // phpredis with OPT_PREFIX can return \Redis object instead of array
        return is_array($result) ? $result : [];
    }

    /**
     * Get information on all of the supervisors.
     *
     * @return array<int, stdClass>
     */
    public function all()
    {
        return $this->get($this->names());
    }

    /**
     * Get information on a supervisor by name.
     *
     * @param string $name
     *
     * @return stdClass|null
     */
    public function find($name)
    {
        // @codeCoverageIgnoreStart — Horizon process management
        return Arr::get($this->get([$name]), 0);
        // @codeCoverageIgnoreEnd
    }

    /**
     * Get information on the given supervisors.
     *
     * @param array<int, string> $names
     *
     * @return array<int, stdClass>
     */
    public function get(array $names)
    {
        $records = $this->connection()->pipeline(function ($pipe) use ($names) {
            foreach ($names as $name) {
                $pipe->hmget('supervisor:'.$name, ['name', 'master', 'pid', 'status', 'processes', 'options']);
            }
        });

        /** @var array<int, mixed> $records */
        return collect($records)
            ->filter()
            ->map(function ($record) {
                $record = array_values($record);

                return ($record[0] === null || $record[0] === false || $record[0] === '') ? null : (object) [
                    'name' => $record[0],
                    'master' => $record[1],
                    'pid' => $record[2],
                    'status' => $record[3],
                    'processes' => json_decode($record[4], true),
                    'options' => json_decode($record[5], true),
                ];
            })
            ->filter()
            ->all();
    }

    /**
     * Get the longest active timeout setting for a supervisor.
     *
     * @return int
     */
    public function longestActiveTimeout()
    {
        // @codeCoverageIgnoreStart — Horizon process management
        $maxTimeout = collect($this->all())
            ->max(fn ($supervisor) => $supervisor->options['timeout']);

        return $maxTimeout !== null ? $maxTimeout : 0;
        // @codeCoverageIgnoreEnd
    }

    /**
     * Update the information about the given supervisor process.
     */
    public function update(Supervisor $supervisor)
    {
        // @codeCoverageIgnoreStart — Horizon process management
        $processes = $supervisor->processPools
            ->mapWithKeys(fn ($pool) => [$supervisor->options->connection.':'.$pool->queue() => count($pool->processes())])
            ->toJson();

        $this->connection()->pipeline(function ($pipe) use ($supervisor, $processes) {
            $pipe->hmset(
                'supervisor:'.$supervisor->name, [
                    'name' => $supervisor->name,
                    'master' => implode(':', explode(':', $supervisor->name, -1)),
                    'pid' => $supervisor->pid(),
                    'status' => $supervisor->working ? 'running' : 'paused',
                    'processes' => $processes,
                    'options' => $supervisor->options->toJson(),
                ]
            );

            $pipe->zadd('supervisors',
                CarbonImmutable::now()->getTimestamp(), $supervisor->name
            );

            $pipe->expire('supervisor:'.$supervisor->name, 30);
        });
        // @codeCoverageIgnoreEnd
    }

    /**
     * Remove the supervisor information from storage.
     *
     * @param array<int, string>|string $names
     */
    public function forget($names)
    {
        // @codeCoverageIgnoreStart — Horizon process management
        $names = (array) $names;

        if ($names === []) {
            return;
        }

        $this->connection()->del(...collect($names)->map(function ($name) {
            return 'supervisor:'.$name;
        })->all());

        $this->connection()->zrem('supervisors', ...$names);
        // @codeCoverageIgnoreEnd
    }

    /**
     * Remove expired supervisors from storage.
     */
    public function flushExpired()
    {
        // @codeCoverageIgnoreStart — Horizon process management
        $this->connection()->zremrangebyscore('supervisors', '-inf',
            (string) CarbonImmutable::now()->subSeconds(14)->getTimestamp()
        );
        // @codeCoverageIgnoreEnd
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
