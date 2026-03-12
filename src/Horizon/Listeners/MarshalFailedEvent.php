<?php

namespace Aicl\Horizon\Listeners;

use Aicl\Horizon\Events\JobFailed;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Queue\Events\JobFailed as LaravelJobFailed;
use Illuminate\Queue\Jobs\RedisJob;

class MarshalFailedEvent
{
    /**
     * The event dispatcher implementation.
     *
     * @var Dispatcher
     */
    public $events;

    /**
     * Create a new listener instance.
     *
     * @return void
     */
    public function __construct(Dispatcher $events)
    {
        $this->events = $events;
    }

    /**
     * Handle the event.
     *
     * @return void
     */
    public function handle(LaravelJobFailed $event)
    {
        if (! $event->job instanceof RedisJob) {
            return;
        }

        $this->events->dispatch((new JobFailed(
            $event->exception, $event->job, $event->job->getReservedJob()
        ))->connection($event->connectionName)->queue($event->job->getQueue()));
    }
}
