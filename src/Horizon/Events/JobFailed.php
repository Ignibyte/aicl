<?php

declare(strict_types=1);

namespace Aicl\Horizon\Events;

use Illuminate\Queue\Jobs\Job;

/**
 * JobFailed.
 */
class JobFailed extends RedisEvent
{
    /**
     * The exception that caused the failure.
     *
     * @var \Exception
     */
    public $exception;

    /**
     * The queue job instance.
     *
     * @var Job
     */
    public $job;

    /**
     * Create a new event instance.
     *
     * @param  \Exception  $exception
     * @param  Job  $job
     * @param  string  $payload
     * @return void
     */
    public function __construct($exception, $job, $payload)
    {
        $this->job = $job;
        $this->exception = $exception;

        parent::__construct($payload);
    }
}
