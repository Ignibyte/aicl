<?php

declare(strict_types=1);

namespace Aicl\Horizon;

use Aicl\Horizon\Contracts\MasterSupervisorRepository;
use Aicl\Horizon\Contracts\SupervisorRepository;
use Illuminate\Support\Arr;

/**
 * ProcessInspector.
 */
class ProcessInspector
{
    /**
     * The command executor.
     *
     * @var Exec
     */
    public $exec;

    /**
     * Create a new process inspector instance.
     */
    public function __construct(Exec $exec)
    {
        $this->exec = $exec;
    }

    /**
     * Get the IDs of all Horizon processes running on the system.
     *
     * @return array<int, string>
     */
    public function current()
    {
        return array_diff(
            $this->exec->run('pgrep -f [a]icl:horizon'),
            $this->exec->run('pgrep -f aicl:horizon:purge')
        );
    }

    /**
     * Get an array of running Horizon processes that can't be accounted for.
     *
     * @return array<int, string>
     */
    public function orphaned()
    {
        // @codeCoverageIgnoreStart — Horizon process management
        return array_diff($this->current(), $this->monitoring());
        // @codeCoverageIgnoreEnd
    }

    /**
     * Get all of the process IDs Horizon is actively monitoring.
     *
     * @return array<int, mixed>
     */
    public function monitoring()
    {
        // @codeCoverageIgnoreStart — Horizon process management
        return collect(app(SupervisorRepository::class)->all())
            ->pluck('pid')
            ->pipe(function ($processes) {
                $processes->each(function ($process) use (&$processes) {
                    $pid = (int) $process;
                    $processes = $processes->merge($this->exec->run('pgrep -P '.escapeshellarg((string) $pid)));
                });

                return $processes;
            })
            ->merge(Arr::pluck(app(MasterSupervisorRepository::class)->all(), 'pid'))
            ->all();
        // @codeCoverageIgnoreEnd
    }
}
