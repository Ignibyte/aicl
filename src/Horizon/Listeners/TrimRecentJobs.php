<?php

declare(strict_types=1);

namespace Aicl\Horizon\Listeners;

use Aicl\Horizon\Contracts\JobRepository;
use Aicl\Horizon\Events\MasterSupervisorLooped;
use Carbon\CarbonImmutable;

/**
 * @codeCoverageIgnore Horizon process management
 */
class TrimRecentJobs
{
    /**
     * The last time the recent jobs were trimmed.
     *
     * @var CarbonImmutable
     */
    public $lastTrimmed;

    /**
     * How many minutes to wait in between each trim.
     *
     * @var int
     */
    public $frequency = 1;

    /**
     * Handle the event.
     */
    public function handle(MasterSupervisorLooped $event)
    {
        if (! isset($this->lastTrimmed)) {
            $this->lastTrimmed = CarbonImmutable::now()->subMinutes($this->frequency + 1);
        }

        if ($this->lastTrimmed->lte(CarbonImmutable::now()->subMinutes($this->frequency))) {
            app(JobRepository::class)->trimRecentJobs();

            $this->lastTrimmed = CarbonImmutable::now();
        }
    }
}
