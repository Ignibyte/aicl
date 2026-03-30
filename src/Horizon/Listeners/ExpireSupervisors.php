<?php

declare(strict_types=1);

namespace Aicl\Horizon\Listeners;

use Aicl\Horizon\Contracts\MasterSupervisorRepository;
use Aicl\Horizon\Contracts\SupervisorRepository;
use Aicl\Horizon\Events\MasterSupervisorLooped;

/**
 * @codeCoverageIgnore Horizon process management
 */
class ExpireSupervisors
{
    /**
     * Handle the event.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function handle(MasterSupervisorLooped $event)
    {
        app(MasterSupervisorRepository::class)->flushExpired();

        app(SupervisorRepository::class)->flushExpired();
    }
}
