<?php

declare(strict_types=1);

namespace Aicl\Horizon\Listeners;

use Aicl\Horizon\Events\SupervisorLooped;

/**
 * PruneTerminatingProcesses.
 */
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
