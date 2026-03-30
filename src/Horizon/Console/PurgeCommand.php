<?php

declare(strict_types=1);

namespace Aicl\Horizon\Console;

use Aicl\Horizon\Contracts\MasterSupervisorRepository;
use Aicl\Horizon\Contracts\ProcessRepository;
use Aicl\Horizon\Contracts\SupervisorRepository;
use Aicl\Horizon\MasterSupervisor;
use Aicl\Horizon\ProcessInspector;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * Terminates any rogue Horizon processes not tracked by a master supervisor.
 *
 * @codeCoverageIgnore Horizon process management
 */
#[AsCommand(name: 'aicl:horizon:purge')]
/**
 * PurgeCommand.
 */
class PurgeCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'aicl:horizon:purge
                            {--signal=SIGTERM : The signal to send to the rogue processes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Terminate any rogue Horizon processes';

    /**
     * @var SupervisorRepository
     */
    private $supervisors;

    /**
     * @var ProcessRepository
     */
    private $processes;

    /**
     * @var ProcessInspector
     */
    private $inspector;

    /**
     * Create a new command instance.
     */
    public function __construct(
        SupervisorRepository $supervisors,
        ProcessRepository $processes,
        ProcessInspector $inspector,
    ) {
        parent::__construct();

        $this->supervisors = $supervisors;
        $this->processes = $processes;
        $this->inspector = $inspector;
    }

    /**
     * Execute the console command.
     */
    public function handle(MasterSupervisorRepository $masters)
    {
        $signal = is_numeric($signal = $this->option('signal'))
            ? $signal
            : constant((string) $signal);

        foreach ($masters->names() as $master) {
            if (Str::startsWith($master, MasterSupervisor::basename())) {
                $this->purge($master, $signal);
            }
        }
    }

    /**
     * Purge any orphan processes.
     *
     * @param string $master
     * @param int    $signal
     */
    public function purge($master, $signal = SIGTERM)
    {
        $this->recordOrphans($master, $signal);

        $expired = $this->processes->orphanedFor(
            $master, $this->supervisors->longestActiveTimeout()
        );

        collect($expired)
            ->whenNotEmpty(function ($collection) use ($master) {
                $this->components->info('Sending TERM signal to expired processes of ['.$master.']');

                return $collection;
            })
            ->each(function ($processId) use ($master, $signal) {
                $this->components->task("Process: $processId", function () use ($processId, $signal) {
                    exec("kill -s {$signal} {$processId}");
                });

                $this->processes->forgetOrphans($master, [$processId]);
            })->whenNotEmpty(fn () => $this->output->writeln(''));
    }

    /**
     * Record the orphaned Horizon processes.
     *
     * @param string $master
     * @param int    $signal
     */
    protected function recordOrphans($master, $signal)
    {
        $this->processes->orphaned(
            $master, $orphans = $this->inspector->orphaned()
        );

        collect($orphans)
            ->whenNotEmpty(function ($collection) use ($master) {
                $this->components->info('Sending TERM signal to orphaned processes of ['.$master.']');

                return $collection;
            })
            ->each(function ($processId) use ($signal) {
                $result = true;

                $this->components->task("Process: $processId", function () use ($processId, $signal, &$result) {
                    return $result = posix_kill($processId, $signal);
                });

                if (! $result) {
                    $this->components->error("Failed to kill orphan process: {$processId} (".posix_strerror(posix_get_last_error()).')');
                }
            })->whenNotEmpty(fn () => $this->output->writeln(''));
    }
}
