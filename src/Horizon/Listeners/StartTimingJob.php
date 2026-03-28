<?php

declare(strict_types=1);

namespace Aicl\Horizon\Listeners;

use Aicl\Horizon\Events\JobReserved;
use Aicl\Horizon\Stopwatch;

/**
 * StartTimingJob.
 */
class StartTimingJob
{
    /**
     * The stopwatch instance.
     *
     * @var Stopwatch
     */
    public $watch;

    /**
     * Create a new listener instance.
     *
     * @return void
     */
    public function __construct(Stopwatch $watch)
    {
        $this->watch = $watch;
    }

    /**
     * Handle the event.
     *
     * @return void
     */
    public function handle(JobReserved $event)
    {
        $this->watch->start($event->payload->id());
    }
}
