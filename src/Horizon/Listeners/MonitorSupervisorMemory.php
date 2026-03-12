<?php

namespace Aicl\Horizon\Listeners;

use Aicl\Horizon\Events\SupervisorLooped;
use Aicl\Horizon\Events\SupervisorOutOfMemory;

class MonitorSupervisorMemory
{
    /**
     * Handle the event.
     *
     * @return void
     */
    public function handle(SupervisorLooped $event)
    {
        $supervisor = $event->supervisor;

        if (($memoryUsage = $supervisor->memoryUsage()) > $supervisor->options->memory) {
            event((new SupervisorOutOfMemory($supervisor))->setMemoryUsage($memoryUsage));

            $supervisor->terminate(12);
        }
    }
}
