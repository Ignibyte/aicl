<?php

namespace Aicl\Horizon\Listeners;

use Aicl\Horizon\Contracts\MasterSupervisorRepository;
use Aicl\Horizon\Contracts\SupervisorRepository;
use Aicl\Horizon\Events\MasterSupervisorLooped;

class ExpireSupervisors
{
    /**
     * Handle the event.
     *
     * @return void
     */
    public function handle(MasterSupervisorLooped $event)
    {
        app(MasterSupervisorRepository::class)->flushExpired();

        app(SupervisorRepository::class)->flushExpired();
    }
}
