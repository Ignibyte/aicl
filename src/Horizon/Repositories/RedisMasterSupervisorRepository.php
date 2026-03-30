<?php

declare(strict_types=1);

namespace Aicl\Horizon\Repositories;

use Aicl\Horizon\Contracts\MasterSupervisorRepository;
use Aicl\Horizon\Contracts\SupervisorRepository;
use Aicl\Horizon\MasterSupervisor;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Redis\Factory;
use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Illuminate\Redis\Connections\Connection;
use Illuminate\Support\Arr;
use stdClass;

/**
 * Redis-backed repository for Horizon master supervisor state.
 *
 * Tracks master supervisor heartbeats and metadata in Redis sorted
 * sets via the 'horizon' connection.
 */
class RedisMasterSupervisorRepository implements MasterSupervisorRepository
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
     * Get the names of all the master supervisors currently running.
     *
     * @return array<int, string>
     */
    public function names()
    {
        // @codeCoverageIgnoreStart — Horizon process management
        $result = $this->connection()->zrevrangebyscore('masters', '+inf',
            (string) CarbonImmutable::now()->subSeconds(14)->getTimestamp()
        );

        return is_array($result) ? $result : [];
        // @codeCoverageIgnoreEnd
    }

    /**
     * Get information on all of the supervisors.
     *
     * @return array<int, stdClass>
     */
    public function all()
    {
        // @codeCoverageIgnoreStart — Horizon process management
        return $this->get($this->names());
        // @codeCoverageIgnoreEnd
    }

    /**
     * Get information on a master supervisor by name.
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
     * Get information on the given master supervisors.
     *
     * @param array<int, string> $names
     *
     * @return array<int, stdClass>
     */
    public function get(array $names)
    {
        // @codeCoverageIgnoreStart — Horizon process management
        $records = $this->connection()->pipeline(function ($pipe) use ($names) {
            foreach ($names as $name) {
                $pipe->hmget('master:'.$name, ['name', 'pid', 'status', 'supervisors', 'environment']);
            }
        });

        /** @var array<int, mixed> $records */
        return collect($records)
            ->map(function ($record) {
                if (! is_array($record)) {
                    return null;
                }

                $record = array_values($record);

                return ($record[0] === null || $record[0] === false || $record[0] === '') ? null : (object) [
                    'name' => $record[0],
                    'environment' => $record[4],
                    'pid' => $record[1],
                    'status' => $record[2],
                    'supervisors' => json_decode($record[3], true),
                ];
            })
            ->filter()
            ->all();
        // @codeCoverageIgnoreEnd
    }

    /**
     * Update the information about the given master supervisor.
     */
    public function update(MasterSupervisor $master)
    {
        // @codeCoverageIgnoreStart — Horizon process management
        $supervisors = $master->supervisors->map->name->all();

        $this->connection()->pipeline(function ($pipe) use ($master, $supervisors) {
            $pipe->hmset(
                'master:'.$master->name, [
                    'name' => $master->name,
                    'environment' => $master->environment,
                    'pid' => $master->pid(),
                    'status' => $master->working ? 'running' : 'paused',
                    'supervisors' => json_encode($supervisors),
                ]
            );

            $pipe->zadd('masters',
                CarbonImmutable::now()->getTimestamp(), $master->name
            );

            $pipe->expire('master:'.$master->name, 15);
        });
        // @codeCoverageIgnoreEnd
    }

    /**
     * Remove the master supervisor information from storage.
     *
     * @param string $name
     *
     * @SuppressWarnings(PHPMD.IfStatementAssignment)
     */
    public function forget($name)
    {
        // @codeCoverageIgnoreStart — Horizon process management
        $master = $this->find($name);

        if ($master === null) {
            return;
        }

        app(SupervisorRepository::class)->forget(
            $master->supervisors
        );

        $this->connection()->del('master:'.$name);

        $this->connection()->zrem('masters', $name);
        // @codeCoverageIgnoreEnd
    }

    /**
     * Remove expired master supervisors from storage.
     */
    public function flushExpired()
    {
        // @codeCoverageIgnoreStart — Horizon process management
        $this->connection()->zremrangebyscore('masters', '-inf',
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
        // @codeCoverageIgnoreStart — Horizon process management
        return $this->redis->connection('horizon');
        // @codeCoverageIgnoreEnd
    }
}
