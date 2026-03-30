<?php

declare(strict_types=1);

namespace Aicl\Horizon\Listeners;

use Aicl\Horizon\Contracts\JobRepository;
use Aicl\Horizon\Events\JobPending;

/**
 * StoreJob.
 */
class StoreJob
{
    /**
     * The job repository implementation.
     *
     * @var JobRepository
     */
    public $jobs;

    /**
     * Create a new listener instance.
     */
    public function __construct(JobRepository $jobs)
    {
        $this->jobs = $jobs;
    }

    /**
     * Handle the event.
     */
    public function handle(JobPending $event)
    {
        $this->jobs->pushed(
            $event->connectionName, $event->queue, $event->payload
        );
    }
}
