<?php

declare(strict_types=1);

namespace Aicl\Horizon\Events;

use Aicl\Horizon\MasterSupervisor;

/**
 * MasterSupervisorOutOfMemory.
 */
class MasterSupervisorOutOfMemory
{
    /**
     * The master supervisor instance.
     *
     * @var MasterSupervisor
     */
    public $master;

    /**
     * Create a new event instance.
     */
    public function __construct(MasterSupervisor $master)
    {
        $this->master = $master;
    }
}
