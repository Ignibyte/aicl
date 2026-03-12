<?php

namespace Aicl\Horizon\Events;

use Aicl\Horizon\WorkerProcess;

class WorkerProcessRestarting
{
    /**
     * The worker process instance.
     *
     * @var WorkerProcess
     */
    public $process;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(WorkerProcess $process)
    {
        $this->process = $process;
    }
}
