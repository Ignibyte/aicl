<?php

declare(strict_types=1);

namespace Aicl\Horizon\Events;

/**
 * MasterSupervisorDeployed.
 */
class MasterSupervisorDeployed
{
    /**
     * The master supervisor that was deployed.
     *
     * @var string
     */
    public $master;

    /**
     * Create a new event instance.
     *
     * @param  string  $master
     * @return void
     */
    public function __construct($master)
    {
        $this->master = $master;
    }
}
