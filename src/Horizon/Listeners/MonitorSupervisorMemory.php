<?php

declare(strict_types=1);

namespace Aicl\Horizon\Listeners;

use Aicl\Horizon\Events\SupervisorLooped;
use Aicl\Horizon\Events\SupervisorOutOfMemory;

/**
 * @codeCoverageIgnore Horizon process management
 */
class MonitorSupervisorMemory
{
    /**
     * Handle the event.
     *
     * @SuppressWarnings(PHPMD.IfStatementAssignment)
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
