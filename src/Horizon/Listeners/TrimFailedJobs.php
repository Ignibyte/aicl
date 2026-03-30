<?php

declare(strict_types=1);

namespace Aicl\Horizon\Listeners;

use Aicl\Horizon\Contracts\JobRepository;
use Aicl\Horizon\Events\MasterSupervisorLooped;
use Carbon\CarbonImmutable;

/**
 * @codeCoverageIgnore Horizon process management
 */
class TrimFailedJobs
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
    public $frequency = 5040;

    /**
     * Handle the event.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function handle(MasterSupervisorLooped $event)
    {
        if (! isset($this->lastTrimmed)) {
            $this->frequency = max(1, intdiv(
                config('aicl-horizon.trim.failed', 10080), 12
            ));

            $this->lastTrimmed = CarbonImmutable::now()->subMinutes($this->frequency + 1);
        }

        if ($this->lastTrimmed->lte(CarbonImmutable::now()->subMinutes($this->frequency))) {
            app(JobRepository::class)->trimFailedJobs();

            $this->lastTrimmed = CarbonImmutable::now();
        }
    }
}
