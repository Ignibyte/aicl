<?php

namespace Aicl\Horizon\Listeners;

use Aicl\Horizon\Stopwatch;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;

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
     * @param  JobExceptionOccurred|JobFailed  $event
     * @return void
     */
    public function handle($event)
    {
        $this->watch->forget($event->job->getJobId());
    }
}
