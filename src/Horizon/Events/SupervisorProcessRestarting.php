<?php

declare(strict_types=1);

namespace Aicl\Horizon\Events;

use Aicl\Horizon\SupervisorProcess;

/**
 * SupervisorProcessRestarting.
 */
class SupervisorProcessRestarting
{
    /**
     * The supervisor process instance.
     *
     * @var SupervisorProcess
     */
    public $process;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(SupervisorProcess $process)
    {
        $this->process = $process;
    }
}
