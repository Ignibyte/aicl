<?php

declare(strict_types=1);

namespace Aicl\Horizon\Events;

use Illuminate\Queue\Jobs\Job;

/**
 * JobDeleted.
 */
class JobDeleted extends RedisEvent
{
    /**
     * The queue job instance.
     *
     * @var Job
     */
    public $job;

    /**
     * Create a new event instance.
     *
     * @param  Job  $job
     * @param  string  $payload
     * @return void
     */
    public function __construct($job, $payload)
    {
        $this->job = $job;

        parent::__construct($payload);
    }
}
