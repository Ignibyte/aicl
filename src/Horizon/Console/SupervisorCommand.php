<?php

declare(strict_types=1);

namespace Aicl\Horizon\Console;

use Aicl\Horizon\Supervisor;
use Aicl\Horizon\SupervisorFactory;
use Aicl\Horizon\SupervisorOptions;
use Exception;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * Starts a Horizon supervisor process with the given balancing and worker options.
 *
 * @codeCoverageIgnore Horizon process management
 */
#[AsCommand(name: 'aicl:horizon:supervisor')]
/**
 * SupervisorCommand.
 */
class SupervisorCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'aicl:horizon:supervisor
                            {name : The name of supervisor}
                            {connection : The name of the connection to work}
                            {--balance= : The balancing strategy the supervisor should apply}
                            {--delay=0 : The number of seconds to delay failed jobs (Deprecated)}
                            {--backoff=0 : The number of seconds to wait before retrying a job that encountered an uncaught exception}
                            {--max-jobs=0 : The number of jobs to process before stopping a child process}
                            {--max-time=0 : The maximum number of seconds a child process should run}
                            {--force : Force the worker to run even in maintenance mode}
                            {--max-processes=1 : The maximum number of total workers to start}
                            {--min-processes=1 : The minimum number of workers to assign per queue}
                            {--memory=128 : The memory limit in megabytes}
                            {--nice=0 : The process priority}
                            {--paused : Start the supervisor in a paused state}
                            {--queue= : The names of the queues to work}
                            {--sleep=3 : Number of seconds to sleep when no job is available}
                            {--timeout=60 : The number of seconds a child process can run}
                            {--tries=0 : Number of times to attempt a job before logging it failed}
                            {--auto-scaling-strategy=time : If supervisor should scale by jobs or time to complete}
                            {--balance-cooldown=3 : The number of seconds to wait in between auto-scaling attempts}
                            {--balance-max-shift=1 : The maximum number of processes to increase or decrease per one scaling}
                            {--workers-name=default : The name that should be assigned to the workers}
                            {--parent-id=0 : The parent process ID}
                            {--rest=0 : Number of seconds to rest between jobs}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Start a new supervisor';

    /**
     * Indicates whether the command should be shown in the Artisan command list.
     *
     * @var bool
     */
    protected $hidden = true;

    /**
     * Execute the console command.
     *
     * @return int|null
     */
    public function handle(SupervisorFactory $factory)
    {
        $supervisor = $factory->make(
            $this->supervisorOptions()
        );

        try {
            $supervisor->ensureNoDuplicateSupervisors();
        } catch (Exception $e) {
            $this->components->error('A supervisor with this name is already running.');

            return 13;
        }

        $this->start($supervisor);
    }

    /**
     * Start the given supervisor.
     *
     * @param  Supervisor  $supervisor
     * @return void
     */
    protected function start($supervisor)
    {
        if ($supervisor->options->nice) {
            proc_nice($supervisor->options->nice);
        }

        $supervisor->handleOutputUsing(function ($type, $line) {
            $this->output->write($line);
        });

        $supervisor->working = ! $this->option('paused');

        $balancedWorkerCount = (int) floor(((int) $this->option('min-processes') + (int) $this->option('max-processes')) / 2);
        $supervisor->scale((int) max(
            0, $balancedWorkerCount - $supervisor->totalSystemProcessCount()
        ));

        $supervisor->monitor();
    }

    /**
     * Get the supervisor options.
     *
     * @return SupervisorOptions
     */
    protected function supervisorOptions()
    {
        $backoff = $this->hasOption('backoff')
            ? $this->option('backoff')
            : $this->option('delay');

        $balance = $this->option('balance');

        $autoScalingStrategy = $balance === 'auto' ? $this->option('auto-scaling-strategy') : null;

        return new SupervisorOptions(
            (string) $this->argument('name'),
            (string) $this->argument('connection'),
            $this->getQueue((string) $this->argument('connection')),
            (string) $this->option('workers-name'),
            (string) $balance,
            $backoff,
            $this->option('max-time'),
            $this->option('max-jobs'),
            $this->option('max-processes'),
            $this->option('min-processes'),
            $this->option('memory'),
            $this->option('timeout'),
            $this->option('sleep'),
            $this->option('tries'),
            $this->option('force'),
            $this->option('nice'),
            $this->option('balance-cooldown'),
            $this->option('balance-max-shift'),
            $this->option('parent-id'),
            $this->option('rest'),
            $autoScalingStrategy
        );
    }

    /**
     * Get the queue name for the worker.
     *
     * @param  string  $connection
     * @return string
     */
    protected function getQueue($connection)
    {
        return $this->option('queue') ?: app('config')->get(
            "queue.connections.{$connection}.queue", 'default'
        );
    }
}
