<?php

declare(strict_types=1);

namespace Aicl\Horizon\Repositories;

use Aicl\Horizon\Contracts\JobRepository;
use Aicl\Horizon\JobPayload;
use Aicl\Horizon\LuaScripts;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Redis\Factory;
use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Illuminate\Redis\Connections\Connection;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

/** Redis-backed repository for storing and querying Horizon job records. */
class RedisJobRepository implements JobRepository
{
    /**
     * The Redis connection instance.
     *
     * @var Factory
     */
    public $redis;

    /**
     * The keys stored on the job hashes.
     *
     * @var array<int, string>
     */
    public $keys = [
        'id', 'connection', 'queue', 'name', 'status', 'payload',
        'exception', 'context', 'failed_at', 'completed_at', 'retried_by',
        'reserved_at', 'delay',
    ];

    /**
     * The number of minutes until recently failed jobs should be purged.
     *
     * @var int
     */
    public $recentFailedJobExpires;

    /**
     * The number of minutes until recent jobs should be purged.
     *
     * @var int
     */
    public $recentJobExpires;

    /**
     * The number of minutes until pending jobs should be purged.
     *
     * @var int
     */
    public $pendingJobExpires;

    /**
     * The number of minutes until completed and silenced jobs should be purged.
     *
     * @var int
     */
    public $completedJobExpires;

    /**
     * The number of minutes until failed jobs should be purged.
     *
     * @var int
     */
    public $failedJobExpires;

    /**
     * The number of minutes until monitored jobs should be purged.
     *
     * @var int
     */
    public $monitoredJobExpires;

    /**
     * Create a new repository instance.
     *
     * @return void
     */
    public function __construct(RedisFactory $redis)
    {
        $this->redis = $redis;
        $this->recentJobExpires = (int) config('aicl-horizon.trim.recent', 60);
        $this->pendingJobExpires = (int) config('aicl-horizon.trim.pending', 60);
        $this->completedJobExpires = (int) config('aicl-horizon.trim.completed', 60);
        $this->failedJobExpires = (int) config('aicl-horizon.trim.failed', 10080);
        $this->recentFailedJobExpires = (int) config('aicl-horizon.trim.recent_failed', $this->failedJobExpires);
        $this->monitoredJobExpires = (int) config('aicl-horizon.trim.monitored', 10080);
    }

    /**
     * Get the next job ID that should be assigned.
     *
     * @return string
     */
    public function nextJobId()
    {
        // @codeCoverageIgnoreStart — Horizon process management
        return (string) $this->connection()->incr('job_id');
        // @codeCoverageIgnoreEnd
    }

    /**
     * Get the total count of recent jobs.
     *
     * @return int
     */
    public function totalRecent()
    {
        // @codeCoverageIgnoreStart — Horizon process management
        return $this->connection()->zcard('recent_jobs');
        // @codeCoverageIgnoreEnd
    }

    /**
     * Get the total count of failed jobs.
     *
     * @return int
     */
    public function totalFailed()
    {
        // @codeCoverageIgnoreStart — Horizon process management
        return $this->connection()->zcard('failed_jobs');
        // @codeCoverageIgnoreEnd
    }

    /**
     * Get a chunk of recent jobs.
     *
     * @param  string|null  $afterIndex
     * @return Collection<int, \stdClass>
     */
    public function getRecent($afterIndex = null)
    {
        return $this->getJobsByType('recent_jobs', $afterIndex);
    }

    /**
     * Get a chunk of failed jobs.
     *
     * @param  string|null  $afterIndex
     * @return Collection<int, \stdClass>
     */
    public function getFailed($afterIndex = null)
    {
        // @codeCoverageIgnoreStart — Horizon process management
        return $this->getJobsByType('failed_jobs', $afterIndex);
        // @codeCoverageIgnoreEnd
    }

    /**
     * Get a chunk of pending jobs.
     *
     * @param  string|null  $afterIndex
     * @return Collection<int, \stdClass>
     */
    public function getPending($afterIndex = null)
    {
        return $this->getJobsByType('pending_jobs', $afterIndex);
    }

    /**
     * Get a chunk of completed jobs.
     *
     * @param  string|null  $afterIndex
     * @return Collection<int, \stdClass>
     */
    public function getCompleted($afterIndex = null)
    {
        return $this->getJobsByType('completed_jobs', $afterIndex);
    }

    /**
     * Get a chunk of silenced jobs.
     *
     * @param  string|null  $afterIndex
     * @return Collection<int, \stdClass>
     */
    public function getSilenced($afterIndex = null)
    {
        // @codeCoverageIgnoreStart — Horizon process management
        return $this->getJobsByType('silenced_jobs', $afterIndex);
        // @codeCoverageIgnoreEnd
    }

    /**
     * Get the count of recent jobs.
     *
     * @return int
     */
    public function countRecent()
    {
        // @codeCoverageIgnoreStart — Horizon process management
        return $this->countJobsByType('recent_jobs');
        // @codeCoverageIgnoreEnd
    }

    /**
     * Get the count of failed jobs.
     *
     * @return int
     */
    public function countFailed()
    {
        return $this->countJobsByType('failed_jobs');
    }

    /**
     * Get the count of pending jobs.
     *
     * @return int
     */
    public function countPending()
    {
        return $this->countJobsByType('pending_jobs');
    }

    /**
     * Get the count of completed jobs.
     *
     * @return int
     */
    public function countCompleted()
    {
        // @codeCoverageIgnoreStart — Horizon process management
        return $this->countJobsByType('completed_jobs');
        // @codeCoverageIgnoreEnd
    }

    /**
     * Get the count of silenced jobs.
     *
     * @return int
     */
    public function countSilenced()
    {
        // @codeCoverageIgnoreStart — Horizon process management
        return $this->countJobsByType('silenced_jobs');
        // @codeCoverageIgnoreEnd
    }

    /**
     * Get the count of the recently failed jobs.
     *
     * @return int
     */
    public function countRecentlyFailed()
    {
        // @codeCoverageIgnoreStart — Horizon process management
        return $this->countJobsByType('recent_failed_jobs');
        // @codeCoverageIgnoreEnd
    }

    /**
     * Get a chunk of jobs from the given type set.
     *
     * @param  string  $type
     * @param  string|null  $afterIndex
     * @return Collection<int, \stdClass>
     */
    protected function getJobsByType($type, $afterIndex)
    {
        $afterIndex = $afterIndex === null ? -1 : (int) $afterIndex;

        return $this->getJobs($this->connection()->zrange(
            $type, $afterIndex + 1, $afterIndex + 50
        ), $afterIndex + 1);
    }

    /**
     * Get the number of jobs in a given type set.
     *
     * @param  string  $type
     * @return int
     */
    protected function countJobsByType($type)
    {
        $minutes = $this->minutesForType($type);

        return $this->connection()->zcount(
            $type, '-inf', CarbonImmutable::now()->subMinutes($minutes)->getTimestamp() * -1
        );
    }

    /**
     * Get the number of minutes to count for a given type set.
     *
     * @param  string  $type
     * @return int
     */
    protected function minutesForType($type)
    {
        return match ($type) {
            'failed_jobs' => $this->failedJobExpires,
            // @codeCoverageIgnoreStart — Horizon process management
            'recent_failed_jobs' => $this->recentFailedJobExpires,
            'pending_jobs' => $this->pendingJobExpires,
            'completed_jobs' => $this->completedJobExpires,
            'silenced_jobs' => $this->completedJobExpires,
            // @codeCoverageIgnoreEnd
            default => $this->recentJobExpires,
        };
    }

    /**
     * Retrieve the jobs with the given IDs.
     *
     * @param  array<int, string>  $ids
     * @param  mixed  $indexFrom
     * @return Collection<int, \stdClass>
     */
    public function getJobs(array $ids, $indexFrom = 0)
    {
        $jobs = $this->connection()->pipeline(function ($pipe) use ($ids) {
            foreach ($ids as $id) {
                // @codeCoverageIgnoreStart — Horizon process management
                $pipe->hmget($id, $this->keys);
                // @codeCoverageIgnoreEnd
            }
        });

        /** @var array<int, mixed> $jobs */
        return $this->indexJobs(collect($jobs)->filter(function ($job) {
            // @codeCoverageIgnoreStart — Horizon process management
            $job = is_array($job) ? array_values($job) : null;

            return is_array($job) && $job[0] !== null && $job[0] !== false;
            // @codeCoverageIgnoreEnd
        })->values(), $indexFrom);
    }

    /**
     * Index the given jobs from the given index.
     *
     * @param  Collection<int, mixed>  $jobs
     * @param  int  $indexFrom
     * @return Collection<int, \stdClass>
     */
    protected function indexJobs($jobs, $indexFrom)
    {
        return $jobs->map(function ($job) use (&$indexFrom) {
            // @codeCoverageIgnoreStart — Horizon process management
            $job = (object) array_combine($this->keys, $job);

            $job->index = $indexFrom;

            $indexFrom++;

            return $job;
            // @codeCoverageIgnoreEnd
        });
    }

    /**
     * Insert the job into storage.
     *
     * @param  string  $connection
     * @param  string  $queue
     * @return void
     */
    public function pushed($connection, $queue, JobPayload $payload)
    {
        // @codeCoverageIgnoreStart — Horizon process management
        $this->connection()->pipeline(function ($pipe) use ($connection, $queue, $payload) {
            $this->storeJobReference($pipe, 'recent_jobs', $payload);
            $this->storeJobReference($pipe, 'pending_jobs', $payload);

            $time = str_replace(',', '.', (string) microtime(true));

            $pipe->hmset($payload->id(), [
                'id' => $payload->id(),
                'connection' => $connection,
                'queue' => $queue,
                'name' => $payload->decoded['displayName'],
                'status' => 'pending',
                'payload' => $payload->value,
                'created_at' => $time,
                'updated_at' => $time,
            ]);

            $pipe->expireat(
                $payload->id(), CarbonImmutable::now()->addMinutes($this->pendingJobExpires)->getTimestamp()
            );
        });
        // @codeCoverageIgnoreEnd
    }

    /**
     * Mark the job as reserved.
     *
     * @param  string  $connection
     * @param  string  $queue
     * @return void
     */
    public function reserved($connection, $queue, JobPayload $payload)
    {
        // @codeCoverageIgnoreStart — Horizon process management
        $time = str_replace(',', '.', (string) microtime(true));

        $this->connection()->hmset(
            $payload->id(), [
                'status' => 'reserved',
                'payload' => $payload->value,
                'updated_at' => $time,
                'reserved_at' => $time,
            ]
        );
        // @codeCoverageIgnoreEnd
    }

    /**
     * Mark the job as released / pending.
     *
     * @param  string  $connection
     * @param  string  $queue
     * @param  int  $delay
     * @return void
     */
    public function released($connection, $queue, JobPayload $payload, $delay = 0)
    {
        // @codeCoverageIgnoreStart — Horizon process management
        $this->connection()->hmset(
            $payload->id(), [
                'status' => 'pending',
                'payload' => $payload->value,
                'updated_at' => str_replace(',', '.', (string) microtime(true)),
                'delay' => $delay,
            ]
        );
        // @codeCoverageIgnoreEnd
    }

    /**
     * Mark the job as completed and monitored.
     *
     * @param  string  $connection
     * @param  string  $queue
     * @return void
     */
    public function remember($connection, $queue, JobPayload $payload)
    {
        // @codeCoverageIgnoreStart — Horizon process management
        $this->connection()->pipeline(function ($pipe) use ($connection, $queue, $payload) {
            $this->storeJobReference($pipe, 'monitored_jobs', $payload);

            $pipe->hmset(
                $payload->id(), [
                    'id' => $payload->id(),
                    'connection' => $connection,
                    'queue' => $queue,
                    'name' => $payload->decoded['displayName'],
                    'status' => 'completed',
                    'payload' => $payload->value,
                    'completed_at' => str_replace(',', '.', (string) microtime(true)),
                ]
            );

            $pipe->expireat(
                $payload->id(), CarbonImmutable::now()->addMinutes($this->monitoredJobExpires)->getTimestamp()
            );
        });
        // @codeCoverageIgnoreEnd
    }

    /**
     * Mark the given jobs as released / pending.
     *
     * @param  string  $connection
     * @param  string  $queue
     * @param  Collection<int, JobPayload>  $payloads
     * @return void
     */
    public function migrated($connection, $queue, Collection $payloads)
    {
        // @codeCoverageIgnoreStart — Horizon process management
        $this->connection()->pipeline(function ($pipe) use ($payloads) {
            foreach ($payloads as $payload) {
                $pipe->hmset(
                    $payload->id(), [
                        'status' => 'pending',
                        'payload' => $payload->value,
                        'updated_at' => str_replace(',', '.', (string) microtime(true)),
                        'delay' => 0,
                    ]
                );
            }
        });
        // @codeCoverageIgnoreEnd
    }

    /**
     * Handle the storage of a completed job.
     *
     * @param  bool  $failed
     * @param  bool  $silenced
     * @return void
     */
    public function completed(JobPayload $payload, $failed = false, $silenced = false)
    {
        // @codeCoverageIgnoreStart — Horizon process management
        if ($payload->isRetry()) {
            $this->updateRetryInformationOnParent($payload, $failed);
        }

        $this->connection()->pipeline(function ($pipe) use ($payload, $silenced) {
            $this->storeJobReference($pipe, $silenced ? 'silenced_jobs' : 'completed_jobs', $payload);
            $this->removeJobReference($pipe, 'pending_jobs', $payload);

            $pipe->hmset(
                $payload->id(), [
                    'status' => 'completed',
                    'completed_at' => str_replace(',', '.', (string) microtime(true)),
                ]
            );

            $pipe->expireat($payload->id(), CarbonImmutable::now()->addMinutes($this->completedJobExpires)->getTimestamp());
        });
        // @codeCoverageIgnoreEnd
    }

    /**
     * Update the retry status of a job's parent.
     *
     * @param  bool  $failed
     * @return void
     */
    protected function updateRetryInformationOnParent(JobPayload $payload, $failed)
    {
        // @codeCoverageIgnoreStart — Horizon process management
        $retryOf = $payload->retryOf();

        if ($retryOf === null) {
            return;
        }

        if ($retries = $this->connection()->hget($retryOf, 'retried_by')) {
            $retries = $this->updateRetryStatus(
                $payload, json_decode($retries, true), $failed
            );

            $this->connection()->hset(
                $retryOf, 'retried_by', json_encode($retries)
            );
            // @codeCoverageIgnoreEnd
        }
    }

    /**
     * Update the retry status of a job in a retry array.
     *
     * @param  array<int, array<string, mixed>>  $retries
     * @param  bool  $failed
     * @return array<int, array<string, mixed>>
     */
    protected function updateRetryStatus(JobPayload $payload, $retries, $failed)
    {
        // @codeCoverageIgnoreStart — Horizon process management
        return collect($retries)
            ->map(function ($retry) use ($payload, $failed) {
                return $retry['id'] === $payload->id()
                    ? Arr::set($retry, 'status', $failed ? 'failed' : 'completed')
                    : $retry;
            })
            ->all();
        // @codeCoverageIgnoreEnd
    }

    /**
     * Delete the given monitored jobs by IDs.
     *
     * @param  array<int, string>  $ids
     * @return void
     */
    public function deleteMonitored(array $ids)
    {
        // @codeCoverageIgnoreStart — Horizon process management
        $this->connection()->pipeline(function ($pipe) use ($ids) {
            foreach ($ids as $id) {
                $pipe->expireat($id, CarbonImmutable::now()->addDays(7)->getTimestamp());
            }
        });
        // @codeCoverageIgnoreEnd
    }

    /**
     * Trim the recent job list.
     *
     * @return void
     */
    public function trimRecentJobs()
    {
        // @codeCoverageIgnoreStart — Horizon process management
        $this->connection()->pipeline(function ($pipe) {
            $pipe->zremrangebyscore(
                'recent_jobs',
                (string) (CarbonImmutable::now()->subMinutes($this->recentJobExpires)->getTimestamp() * -1),
                '+inf'
            );

            $pipe->zremrangebyscore(
                'recent_failed_jobs',
                (string) (CarbonImmutable::now()->subMinutes($this->recentFailedJobExpires)->getTimestamp() * -1),
                '+inf'
            );

            $pipe->zremrangebyscore(
                'pending_jobs',
                (string) (CarbonImmutable::now()->subMinutes($this->pendingJobExpires)->getTimestamp() * -1),
                '+inf'
            );

            $pipe->zremrangebyscore(
                'completed_jobs',
                (string) (CarbonImmutable::now()->subMinutes($this->completedJobExpires)->getTimestamp() * -1),
                '+inf'
            );

            $pipe->zremrangebyscore(
                'silenced_jobs',
                (string) (CarbonImmutable::now()->subMinutes($this->completedJobExpires)->getTimestamp() * -1),
                '+inf'
            );
        });
        // @codeCoverageIgnoreEnd
    }

    /**
     * Trim the failed job list.
     *
     * @return void
     */
    public function trimFailedJobs()
    {
        // @codeCoverageIgnoreStart — Horizon process management
        $this->connection()->zremrangebyscore(
            'failed_jobs', (string) (CarbonImmutable::now()->subMinutes($this->failedJobExpires)->getTimestamp() * -1), '+inf'
        );
        // @codeCoverageIgnoreEnd
    }

    /**
     * Trim the monitored job list.
     *
     * @return void
     */
    public function trimMonitoredJobs()
    {
        // @codeCoverageIgnoreStart — Horizon process management
        $this->connection()->zremrangebyscore(
            'monitored_jobs', (string) (CarbonImmutable::now()->subMinutes($this->monitoredJobExpires)->getTimestamp() * -1), '+inf'
        );
        // @codeCoverageIgnoreEnd
    }

    /**
     * Find a failed job by ID.
     *
     * @param  string  $id
     * @return \stdClass|null
     */
    public function findFailed($id)
    {
        // @codeCoverageIgnoreStart — Horizon process management
        $attributes = $this->connection()->hmget(
            $id, $this->keys
        );

        $job = is_array($attributes) && $attributes[0] !== null ? (object) array_combine($this->keys, $attributes) : null;

        if ($job && $job->status !== 'failed') {
            return;
        }

        return $job;
        // @codeCoverageIgnoreEnd
    }

    /**
     * Mark the job as failed.
     *
     * @param  \Exception  $exception
     * @param  string  $connection
     * @param  string  $queue
     * @return void
     */
    public function failed($exception, $connection, $queue, JobPayload $payload)
    {
        // @codeCoverageIgnoreStart — Horizon process management
        $this->connection()->pipeline(function ($pipe) use ($exception, $connection, $queue, $payload) {
            $this->storeJobReference($pipe, 'failed_jobs', $payload);
            $this->storeJobReference($pipe, 'recent_failed_jobs', $payload);
            $this->removeJobReference($pipe, 'pending_jobs', $payload);
            $this->removeJobReference($pipe, 'completed_jobs', $payload);
            $this->removeJobReference($pipe, 'silenced_jobs', $payload);

            $pipe->hmset(
                $payload->id(), [
                    'id' => $payload->id(),
                    'connection' => $connection,
                    'queue' => $queue,
                    'name' => $payload->decoded['displayName'],
                    'status' => 'failed',
                    'payload' => $payload->value,
                    'exception' => (string) $exception,
                    'context' => method_exists($exception, 'context')
                        ? json_encode($exception->context())
                        : null,
                    'failed_at' => str_replace(',', '.', (string) microtime(true)),
                ]
            );

            $pipe->expireat(
                $payload->id(), CarbonImmutable::now()->addMinutes($this->failedJobExpires)->getTimestamp()
            );
        });
        // @codeCoverageIgnoreEnd
    }

    /**
     * Store the look-up references for a job.
     *
     * @param  mixed  $pipe
     * @param  string  $key
     * @return void
     */
    protected function storeJobReference($pipe, $key, JobPayload $payload)
    {
        // @codeCoverageIgnoreStart — Horizon process management
        $pipe->zadd($key, str_replace(',', '.', (string) (microtime(true) * -1)), $payload->id());
        // @codeCoverageIgnoreEnd
    }

    /**
     * Remove the look-up references for a job.
     *
     * @param  mixed  $pipe
     * @param  string  $key
     * @return void
     */
    protected function removeJobReference($pipe, $key, JobPayload $payload)
    {
        // @codeCoverageIgnoreStart — Horizon process management
        $pipe->zrem($key, $payload->id());
        // @codeCoverageIgnoreEnd
    }

    /**
     * Store the retry job ID on the original job record.
     *
     * @param  string  $id
     * @param  string  $retryId
     * @return void
     */
    public function storeRetryReference($id, $retryId)
    {
        // @codeCoverageIgnoreStart — Horizon process management
        $retries = json_decode($this->connection()->hget($id, 'retried_by') ?: '[]');

        $retries[] = [
            'id' => $retryId,
            'status' => 'pending',
            'retried_at' => CarbonImmutable::now()->getTimestamp(),
        ];

        $this->connection()->hmset($id, ['retried_by' => json_encode($retries)]);
        // @codeCoverageIgnoreEnd
    }

    /**
     * Delete a failed job by ID.
     *
     * @param  string  $id
     * @return int
     */
    public function deleteFailed($id)
    {
        // @codeCoverageIgnoreStart — Horizon process management
        return $this->connection()->zrem('failed_jobs', $id) != 1
            ? 0
            : $this->connection()->del($id);
        // @codeCoverageIgnoreEnd
    }

    /**
     * Delete pending and reserved jobs for a queue.
     *
     * @param  string  $queue
     * @return int
     */
    public function purge($queue)
    {
        // @codeCoverageIgnoreStart — Horizon process management
        $count = 0;
        $cursor = 0;

        do {
            $result = $this->connection()->eval(
                LuaScripts::purge(),
                2,
                'recent_jobs',
                'pending_jobs',
                config('aicl-horizon.prefix'),
                $queue,
                $cursor
            );

            $count += $result[0];
            $cursor = $result[1];
        } while ($cursor !== '0');

        return $count;
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
