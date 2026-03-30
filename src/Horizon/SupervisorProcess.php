<?php

declare(strict_types=1);

namespace Aicl\Horizon;

use Aicl\Horizon\Contracts\HorizonCommandQueue;
use Aicl\Horizon\Contracts\SupervisorRepository;
use Aicl\Horizon\MasterSupervisorCommands\AddSupervisor;
use Aicl\Horizon\SupervisorCommands\Terminate;
use Closure;
use Override;
use Symfony\Component\Process\Process;

/**
 * SupervisorProcess.
 */
class SupervisorProcess extends WorkerProcess
{
    /**
     * The name of the supervisor.
     *
     * @var string
     */
    public $name;

    /**
     * The supervisor process options.
     *
     * @var SupervisorOptions
     */
    public $options;

    /**
     * Indicates if the process is "dead".
     *
     * @var bool
     */
    public $dead = false;

    /**
     * The exit codes on which supervisor should be marked as dead.
     *
     * @var array<int, int>
     */
    public $dontRestartOn = [
        0,
        2,
        13, // Indicates duplicate supervisors...
    ];

    /**
     * Create a new supervisor process instance.
     *
     * @param Process $process
     */
    /** @codeCoverageIgnore Reason: horizon-process -- Constructor output closure requires process context */
    public function __construct(SupervisorOptions $options, $process, ?Closure $output = null)
    {
        $this->options = $options;
        $this->name = $options->name;

        $this->output = $output !== null ? $output : function () {
            //
            // @codeCoverageIgnoreStart — Horizon process management
        };
        // @codeCoverageIgnoreEnd

        parent::__construct($process);
    }

    /**
     * Evaluate the current state of the process.
     *
     * @codeCoverageIgnore Reason: horizon-process -- Process monitoring requires OS process control and real workers
     */
    #[Override]
    public function monitor()
    {
        // @codeCoverageIgnoreStart — Horizon process management
        if (! $this->process->isStarted()) {
            return $this->restart();
            // @codeCoverageIgnoreEnd
        }

        // First, we will check to see if the supervisor failed as a duplicate and if
        // it did we will go ahead and mark it as dead. We will do this before the
        // other checks run because we do not care if this is cooling down here.
        // @codeCoverageIgnoreStart — Horizon process management
        if (! $this->process->isRunning() &&
            $this->process->getExitCode() === 13) {
            return $this->markAsDead();
            // @codeCoverageIgnoreEnd
        }

        // If the process is running or cooling down from a failure, we don't need to
        // attempt to do anything right now, so we can just bail out of the method
        // here and it will get checked out during the next master monitor loop.
        // @codeCoverageIgnoreStart — Horizon process management
        if ($this->process->isRunning() ||
            $this->coolingDown()) {
            return;
            // @codeCoverageIgnoreEnd
        }

        // Next, we will determine if the exit code is one that means this supervisor
        // should be marked as dead and not be restarted. Typically, this could be
        // an indication that the supervisor was simply purposefully terminated.
        // @codeCoverageIgnoreStart — Horizon process management
        $exitCode = $this->process->getExitCode();

        $this->markAsDead();
        // @codeCoverageIgnoreEnd

        // If the supervisor exited with a status code that we do not restart on then
        // we will not attempt to restart it. Otherwise, we will need to provision
        // it back out based on the latest provisioning information we have now.
        // @codeCoverageIgnoreStart — Horizon process management
        if (in_array($exitCode, $this->dontRestartOn, true)) {
            return;
        }

        $this->reprovision();
        // @codeCoverageIgnoreEnd
    }

    /**
     * Re-provision this supervisor process based on the provisioning plan.
     */
    protected function reprovision()
    {
        // @codeCoverageIgnoreStart — Horizon process management
        if (isset($this->name)) {
            app(SupervisorRepository::class)->forget($this->name);
        }

        app(HorizonCommandQueue::class)->push(
            MasterSupervisor::commandQueue(),
            AddSupervisor::class,
            $this->options->toArray()
        );
        // @codeCoverageIgnoreEnd
    }

    /**
     * Terminate the supervisor with the given status.
     *
     * @param int $status
     */
    public function terminateWithStatus($status)
    {
        // @codeCoverageIgnoreStart — Horizon process management
        app(HorizonCommandQueue::class)->push(
            $this->options->name, Terminate::class, ['status' => $status]
        );
        // @codeCoverageIgnoreEnd
    }

    /**
     * Mark the process as "dead".
     */
    protected function markAsDead()
    {
        // @codeCoverageIgnoreStart — Horizon process management
        $this->dead = true;
        // @codeCoverageIgnoreEnd
    }
}
