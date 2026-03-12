<?php

namespace Aicl\Horizon\Events;

use Aicl\Horizon\MasterSupervisor;

class MasterSupervisorLooped
{
    /**
     * The master supervisor instance.
     *
     * @var MasterSupervisor
     */
    public $master;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(MasterSupervisor $master)
    {
        $this->master = $master;
    }
}
