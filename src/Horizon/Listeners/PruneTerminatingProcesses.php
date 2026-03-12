<?php

namespace Aicl\Horizon\Listeners;

use Aicl\Horizon\Events\SupervisorLooped;

class PruneTerminatingProcesses
{
    /**
     * Handle the event.
     *
     * @return void
     */
    public function handle(SupervisorLooped $event)
    {
        $event->supervisor->pruneTerminatingProcesses();
    }
}
