<?php

declare(strict_types=1);

namespace Aicl\Horizon\Listeners;

use Aicl\Horizon\Contracts\MetricsRepository;
use Aicl\Horizon\Events\JobDeleted;
use Aicl\Horizon\Stopwatch;

/**
 * UpdateJobMetrics.
 */
class UpdateJobMetrics
{
    /**
     * The metrics repository implementation.
     *
     * @var MetricsRepository
     */
    public $metrics;

    /**
     * The stopwatch instance.
     *
     * @var Stopwatch
     */
    public $watch;

    /**
     * Create a new listener instance.
     */
    public function __construct(MetricsRepository $metrics, Stopwatch $watch)
    {
        $this->watch = $watch;
        $this->metrics = $metrics;
    }

    /**
     * Stop gathering metrics for a job.
     */
    public function handle(JobDeleted $event)
    {
        if ($event->job->hasFailed()) {
            return;
        }

        $time = $this->watch->check($id = $event->payload->id()) ?: 0;

        $this->metrics->incrementQueue(
            $event->job->getQueue(), $time
        );

        $this->metrics->incrementJob(
            $event->payload->displayName(), $time
        );

        $this->watch->forget($id);
    }
}
