<?php

declare(strict_types=1);

namespace Aicl\Horizon\Listeners;

use Aicl\Horizon\Contracts\JobRepository;
use Aicl\Horizon\Events\JobFailed;

/**
 * MarkJobAsFailed.
 */
class MarkJobAsFailed
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
    public function handle(JobFailed $event)
    {
        $this->jobs->failed(
            $event->exception, $event->connectionName,
            $event->queue, $event->payload
        );
    }
}
