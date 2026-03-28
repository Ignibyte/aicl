<?php

declare(strict_types=1);

namespace Aicl\Horizon;

use Aicl\Horizon\Events\JobDeleted;
use Aicl\Horizon\Events\JobPending;
use Aicl\Horizon\Events\JobPushed;
use Aicl\Horizon\Events\JobReleased;
use Aicl\Horizon\Events\JobReserved;
use Aicl\Horizon\Events\JobsMigrated;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\Jobs\RedisJob;
use Illuminate\Queue\RedisQueue as BaseQueue;
use Illuminate\Support\Str;

/** Redis queue driver with Horizon job lifecycle event dispatching. */
class RedisQueue extends BaseQueue
{
    /**
     * The job that last pushed to queue via the "push" method.
     *
     * @var object|string
     */
    protected $lastPushed;

    /**
     * Get the number of queue jobs that are ready to process.
     *
     * @param  string|null  $queue
     * @return int
     */
    public function readyNow($queue = null)
    {
        return $this->getConnection()->llen($this->getQueue($queue));
    }

    /**
     * Push a new job onto the queue.
     *
     * @param  object|string  $job
     * @param  mixed  $data
     * @param  string|null  $queue
     * @return mixed
     */
    #[\Override]
    public function push($job, $data = '', $queue = null)
    {
        // @codeCoverageIgnoreStart — Horizon process management
        return $this->enqueueUsing(
            $job,
            $this->createPayload($job, $this->getQueue($queue), $data),
            $queue,
            null,
            function ($payload, $queue) use ($job) {
                $this->lastPushed = $job;

                return $this->pushRaw($payload, $queue);
            }
        );
        // @codeCoverageIgnoreEnd
    }

    /**
     * Push a raw payload onto the queue.
     *
     * @param  string  $payload
     * @param  string|null  $queue
     * @param  array<string, mixed>  $options
     * @return mixed
     */
    #[\Override]
    public function pushRaw($payload, $queue = null, array $options = [])
    {
        // @codeCoverageIgnoreStart — Horizon process management
        $payload = (new JobPayload($payload))->prepare($this->lastPushed);

        $this->event($this->getQueue($queue), new JobPending($payload->value));

        parent::pushRaw($payload->value, $queue, $options);

        $this->event($this->getQueue($queue), new JobPushed($payload->value));

        return $payload->id();
        // @codeCoverageIgnoreEnd
    }

    /**
     * Create a payload string from the given job and data.
     *
     * @param  string  $job
     * @param  string  $queue
     * @param  mixed  $data
     * @return array<string, mixed>
     */
    #[\Override]
    protected function createPayloadArray($job, $queue, $data = '')
    {
        $payload = parent::createPayloadArray($job, $queue, $data);

        $payload['id'] = $payload['uuid'];

        return $payload;
    }

    /**
     * Push a new job onto the queue after a delay.
     *
     * @param  \DateTimeInterface|\DateInterval|int  $delay
     * @param  string  $job
     * @param  mixed  $data
     * @param  string|null  $queue
     * @return mixed
     */
    #[\Override]
    public function later($delay, $job, $data = '', $queue = null)
    {
        // @codeCoverageIgnoreStart — Horizon process management
        $payload = (new JobPayload($this->createPayload($job, $this->getQueue($queue), $data)))->prepare($job)->value;

        if (method_exists($this, 'enqueueUsing')) {
            return $this->enqueueUsing(
                $job,
                $payload,
                $queue,
                $delay,
                function ($payload, $queue, $delay) {
                    $this->event($this->getQueue($queue), new JobPending($payload));

                    return tap(parent::laterRaw($delay, $payload, $queue), function () use ($payload, $queue) {
                        $this->event($this->getQueue($queue), new JobPushed($payload));
                    });
                }
            );
        }

        $this->event($this->getQueue($queue), new JobPending($payload));

        return tap(parent::laterRaw($delay, $payload, $queue), function () use ($payload, $queue) {
            $this->event($this->getQueue($queue), new JobPushed($payload));
        });
        // @codeCoverageIgnoreEnd
    }

    /**
     * Pop the next job off of the queue.
     *
     * @param  string|null  $queue
     * @param  int  $index
     * @return Job|null
     */
    #[\Override]
    public function pop($queue = null, $index = 0)
    {
        // @codeCoverageIgnoreStart — Horizon process management
        return tap(parent::pop($queue, $index), function ($result) use ($queue) {
            if ($result) {
                $this->event($this->getQueue($queue), new JobReserved($result->getReservedJob()));
            }
        });
        // @codeCoverageIgnoreEnd
    }

    /**
     * Migrate the delayed jobs that are ready to the regular queue.
     *
     * @param  string  $from
     * @param  string  $to
     * @return void
     */
    #[\Override]
    public function migrateExpiredJobs($from, $to)
    {
        // @codeCoverageIgnoreStart — Horizon process management
        return tap(parent::migrateExpiredJobs($from, $to), function ($jobs) use ($to) {
            $this->event($to, new JobsMigrated($jobs === false ? [] : $jobs));
        });
        // @codeCoverageIgnoreEnd
    }

    /**
     * Delete a reserved job from the queue.
     *
     * @param  string  $queue
     * @param  RedisJob  $job
     * @return void
     */
    #[\Override]
    public function deleteReserved($queue, $job)
    {
        // @codeCoverageIgnoreStart — Horizon process management
        parent::deleteReserved($queue, $job);

        $this->event($this->getQueue($queue), new JobDeleted($job, $job->getReservedJob()));
        // @codeCoverageIgnoreEnd
    }

    /**
     * Delete a reserved job from the reserved queue and release it.
     *
     * @param  string  $queue
     * @param  RedisJob  $job
     * @param  int  $delay
     * @return void
     */
    #[\Override]
    public function deleteAndRelease($queue, $job, $delay)
    {
        // @codeCoverageIgnoreStart — Horizon process management
        parent::deleteAndRelease($queue, $job, $delay);

        $this->event($this->getQueue($queue), new JobReleased($job->getReservedJob(), $delay));
        // @codeCoverageIgnoreEnd
    }

    /**
     * Fire the given event if a dispatcher is bound.
     *
     * @param  string  $queue
     * @param  mixed  $event
     * @return void
     */
    protected function event($queue, $event)
    {
        if ($this->container && $this->container->bound(Dispatcher::class)) {
            $queue = Str::replaceFirst('queues:', '', $queue);

            $this->container->make(Dispatcher::class)->dispatch(
                $event->connection($this->getConnectionName())->queue($queue)
            );
        }
    }
}
