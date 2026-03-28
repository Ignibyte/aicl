<?php

declare(strict_types=1);

namespace Aicl\Horizon\Listeners;

use Aicl\Horizon\Contracts\JobRepository;
use Aicl\Horizon\Events\JobsMigrated;

/**
 * MarkJobsAsMigrated.
 */
class MarkJobsAsMigrated
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
    public function handle(JobsMigrated $event)
    {
        $this->jobs->migrated($event->connectionName, $event->queue, $event->payloads);
    }
}
