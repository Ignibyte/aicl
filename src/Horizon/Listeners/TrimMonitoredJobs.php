<?php

declare(strict_types=1);

namespace Aicl\Horizon\Listeners;

use Aicl\Horizon\Contracts\JobRepository;
use Aicl\Horizon\Events\MasterSupervisorLooped;
use Carbon\CarbonImmutable;

/**
 * @codeCoverageIgnore Horizon process management
 */
class TrimMonitoredJobs
{
    /**
     * The last time the monitored jobs were trimmed.
     *
     * @var CarbonImmutable
     */
    public $lastTrimmed;

    /**
     * How many minutes to wait in between each trim.
     *
     * @var int
     */
    public $frequency = 1440;

    /**
     * Handle the event.
     *
     * @return void
     */
    public function handle(MasterSupervisorLooped $event)
    {
        if (! isset($this->lastTrimmed)) {
            $this->frequency = max(1, intdiv(
                config('aicl-horizon.trim.monitored', 10080), 12
            ));

            $this->lastTrimmed = CarbonImmutable::now()->subMinutes($this->frequency + 1);
        }

        if ($this->lastTrimmed->lte(CarbonImmutable::now()->subMinutes($this->frequency))) {
            app(JobRepository::class)->trimMonitoredJobs();

            $this->lastTrimmed = CarbonImmutable::now();
        }
    }
}
