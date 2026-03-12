<?php

namespace Aicl\Horizon\Listeners;

use Aicl\Horizon\Contracts\JobRepository;
use Aicl\Horizon\Events\JobReleased;

class MarkJobAsReleased
{
    /**
     * The job repository implementation.
     *
     * @var JobRepository
     */
    public $jobs;

    /**
     * Create a new listener instance.
     *
     * @return void
     */
    public function __construct(JobRepository $jobs)
    {
        $this->jobs = $jobs;
    }

    /**
     * Handle the event.
     *
     * @return void
     */
    public function handle(JobReleased $event)
    {
        $this->jobs->released($event->connectionName, $event->queue, $event->payload, $event->delay);
    }
}
