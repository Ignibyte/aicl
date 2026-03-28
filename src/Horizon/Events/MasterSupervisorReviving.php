<?php

declare(strict_types=1);

namespace Aicl\Horizon\Events;

/**
 * MasterSupervisorReviving.
 */
class MasterSupervisorReviving
{
    /**
     * The master supervisor that was dead.
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
