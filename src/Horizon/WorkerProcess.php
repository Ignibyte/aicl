<?php

declare(strict_types=1);

namespace Aicl\Horizon;

use Aicl\Horizon\Events\UnableToLaunchProcess;
use Aicl\Horizon\Events\WorkerProcessRestarting;
use Carbon\CarbonImmutable;
use Closure;
use Symfony\Component\Process\Exception\ExceptionInterface;
use Symfony\Component\Process\Process;

/** Wraps a Symfony Process for a Horizon queue worker with restart and cooldown logic. */
class WorkerProcess
{
    /**
     * The underlying Symfony process.
     *
     * @var Process
     */
    public $process;

    /**
     * The output handler callback.
     *
     * @var Closure
     */
    public $output;

    /**
     * The time at which the cooldown period will be over.
     *
     * @var CarbonImmutable|null
     */
    public $restartAgainAt;

    /**
     * Create a new worker process instance.
     *
     * @param Process $process
     */
    public function __construct($process)
    {
        $this->process = $process;
    }

    /**
     * Start the process.
     *
     * @return $this
     */
    public function start(Closure $callback)
    {
        // @codeCoverageIgnoreStart — Horizon process management
        $this->output = $callback;

        $this->cooldown();

        $this->process->start($callback);

        return $this;
        // @codeCoverageIgnoreEnd
    }

    /**
     * Pause the worker process.
     */
    public function pause()
    {
        $this->sendSignal(SIGUSR2);
    }

    /**
     * Instruct the worker process to continue working.
     */
    public function continue()
    {
        $this->sendSignal(SIGCONT);
    }

    /**
     * Evaluate the current state of the process.
     */
    public function monitor()
    {
        if ($this->process->isRunning() || ($this->coolingDown() && $this->process->getExitCode() !== 0)) {
            return;
        }

        // @codeCoverageIgnoreStart — Horizon process management
        $this->restart();
        // @codeCoverageIgnoreEnd
    }

    /**
     * Restart the process.
     */
    protected function restart()
    {
        // @codeCoverageIgnoreStart — Horizon process management
        if ($this->process->isStarted()) {
            event(new WorkerProcessRestarting($this));
        }

        $this->start($this->output);
        // @codeCoverageIgnoreEnd
    }

    /**
     * Terminate the underlying process.
     */
    public function terminate()
    {
        $this->sendSignal(SIGTERM);
    }

    /**
     * Stop the underlying process.
     */
    public function stop()
    {
        if ($this->process->isRunning()) {
            $this->process->stop();
        }
    }

    /**
     * Send a POSIX signal to the process.
     *
     * @param int $signal
     */
    protected function sendSignal($signal)
    {
        try {
            $this->process->signal($signal);
        } catch (ExceptionInterface $e) {
            if ($this->process->isRunning()) {
                throw $e;
            }
        }
    }

    /**
     * Begin the cool-down period for the process.
     */
    protected function cooldown()
    {
        // @codeCoverageIgnoreStart — Horizon process management
        if ($this->coolingDown()) {
            return;
        }

        if ($this->restartAgainAt) {
            $this->restartAgainAt = ! $this->process->isRunning()
                ? CarbonImmutable::now()->addMinute()
                : null;

            if (! $this->process->isRunning()) {
                event(new UnableToLaunchProcess($this));
            }
        } else {
            $this->restartAgainAt = CarbonImmutable::now()->addSecond();
            // @codeCoverageIgnoreEnd
        }
    }

    /**
     * Determine if the process is cooling down from a failed restart.
     *
     * @return bool
     */
    public function coolingDown()
    {
        return isset($this->restartAgainAt) &&
            CarbonImmutable::now()->lt($this->restartAgainAt);
    }

    /**
     * Set the output handler.
     *
     * @return $this
     */
    public function handleOutputUsing(Closure $callback)
    {
        $this->output = $callback;

        return $this;
    }

    /**
     * Pass on method calls to the underlying process.
     *
     * @param array<int, mixed> $parameters
     */
    public function __call(string $method, array $parameters): mixed
    {
        return $this->process->{$method}(...$parameters);
    }
}
