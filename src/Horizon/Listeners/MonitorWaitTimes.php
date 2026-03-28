<?php

declare(strict_types=1);

namespace Aicl\Horizon\Listeners;

use Aicl\Horizon\Contracts\MetricsRepository;
use Aicl\Horizon\Events\LongWaitDetected;
use Aicl\Horizon\WaitTimeCalculator;
use Carbon\CarbonImmutable;

/**
 * MonitorWaitTimes.
 */
class MonitorWaitTimes
{
    /**
     * The metrics repository implementation.
     *
     * @var MetricsRepository
     */
    public $metrics;

    /**
     * The time at which we last checked if monitoring was due.
     *
     * @var CarbonImmutable
     */
    public $lastMonitored;

    /**
     * Create a new listener instance.
     *
     * @return void
     */
    public function __construct(MetricsRepository $metrics)
    {
        $this->metrics = $metrics;
    }

    /**
     * Handle the event.
     *
     * @return void
     */
    public function handle()
    {
        // @codeCoverageIgnoreStart — Horizon process management
        if (! $this->dueToMonitor()) {
            return;
            // @codeCoverageIgnoreEnd
        }

        // Here we will calculate the wait time in seconds for each of the queues that
        // the application is working. Then, we will filter the results to find the
        // queues with the longest wait times and raise events for each of these.
        // @codeCoverageIgnoreStart — Horizon process management
        $results = app(WaitTimeCalculator::class)->calculate();

        $long = collect($results)->filter(function ($wait, $queue) {
            return config("horizon.waits.{$queue}") !== 0
                    && $wait > (config("horizon.waits.{$queue}") ?? 60);
        });
        // @codeCoverageIgnoreEnd

        // Once we have determined which queues have long wait times we will raise the
        // events for each of the queues. We'll need to separate the connection and
        // queue names into their own strings before we will fire off the events.
        // @codeCoverageIgnoreStart — Horizon process management
        $long->each(function ($wait, $queue) {
            [$connection, $queue] = explode(':', $queue, 2);

            event(new LongWaitDetected($connection, $queue, $wait));
        });
        // @codeCoverageIgnoreEnd
    }

    /**
     * Determine if monitoring is due.
     *
     * @return bool
     */
    protected function dueToMonitor()
    {
        // We will keep track of the amount of time between attempting to acquire the
        // lock to monitor the wait times. We only want a single supervisor to run
        // the checks on a given interval so that we don't fire too many events.
        // @codeCoverageIgnoreStart — Horizon process management
        if (! $this->timeToMonitor()) {
            return false;
        }

        $lock = $this->metrics->acquireWaitTimeMonitorLock();

        if (! $lock) {
            return false;
            // @codeCoverageIgnoreEnd
        }

        // Next we will update the monitor timestamp and attempt to acquire a lock to
        // check the wait times. We use Redis to do it in order to have the atomic
        // operation required. This will avoid any deadlocks or race conditions.
        // @codeCoverageIgnoreStart — Horizon process management
        $this->lastMonitored = CarbonImmutable::now();

        return true;
        // @codeCoverageIgnoreEnd
    }

    /**
     * Determine if enough time has elapsed to attempt to monitor.
     *
     * @return bool
     */
    protected function timeToMonitor()
    {
        // @codeCoverageIgnoreStart — Horizon process management
        if ($this->lastMonitored === null) {
            return true;
        }

        return CarbonImmutable::now()->gte($this->lastMonitored->addMinute());
        // @codeCoverageIgnoreEnd
    }
}
