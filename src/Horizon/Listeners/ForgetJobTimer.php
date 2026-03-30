<?php

declare(strict_types=1);

namespace Aicl\Horizon\Listeners;

use Aicl\Horizon\Stopwatch;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;

/**
 * ForgetJobTimer.
 */
class ForgetJobTimer
{
    /**
     * The stopwatch instance.
     *
     * @var Stopwatch
     */
    public $watch;

    /**
     * Create a new listener instance.
     */
    public function __construct(Stopwatch $watch)
    {
        $this->watch = $watch;
    }

    /**
     * Handle the event.
     *
     * @param JobExceptionOccurred|JobFailed $event
     */
    public function handle($event)
    {
        $this->watch->forget($event->job->getJobId());
    }
}
