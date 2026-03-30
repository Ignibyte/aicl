<?php

declare(strict_types=1);

namespace Aicl\Horizon\Events;

use Aicl\Horizon\WorkerProcess;

/**
 * UnableToLaunchProcess.
 */
class UnableToLaunchProcess
{
    /**
     * The worker process instance.
     *
     * @var WorkerProcess
     */
    public $process;

    /**
     * Create a new event instance.
     */
    public function __construct(WorkerProcess $process)
    {
        $this->process = $process;
    }
}
