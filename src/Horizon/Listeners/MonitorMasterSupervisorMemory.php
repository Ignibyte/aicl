<?php

declare(strict_types=1);

namespace Aicl\Horizon\Listeners;

use Aicl\Horizon\Events\MasterSupervisorLooped;
use Aicl\Horizon\Events\MasterSupervisorOutOfMemory;

/**
 * @codeCoverageIgnore Horizon process management
 */
class MonitorMasterSupervisorMemory
{
    /**
     * Handle the event.
     */
    public function handle(MasterSupervisorLooped $event)
    {
        $master = $event->master;

        $memoryLimit = config('aicl-horizon.memory_limit', 64);

        if ($master->memoryUsage() > $memoryLimit) {
            event(new MasterSupervisorOutOfMemory($master));

            $master->output('error', 'Memory limit exceeded: Using '.ceil($master->memoryUsage()).'/'.$memoryLimit.'MB. Consider increasing horizon.memory_limit.');

            $master->terminate(12);
        }
    }
}
