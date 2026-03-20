<?php

declare(strict_types=1);

namespace Aicl\Horizon\Console;

use Aicl\Horizon\Contracts\JobRepository;
use Aicl\Horizon\RedisQueue;
use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Illuminate\Queue\QueueManager;
use Illuminate\Support\Arr;
use Symfony\Component\Console\Attribute\AsCommand;

/** Clears all jobs from a specified Horizon queue connection. */
#[AsCommand(name: 'aicl:horizon:clear')]
class ClearCommand extends Command
{
    use ConfirmableTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'aicl:horizon:clear
                            {connection? : The name of the queue connection}
                            {--queue= : The name of the queue to clear}
                            {--force : Force the operation to run when in production}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete all of the jobs from the specified queue';

    /**
     * Execute the console command.
     *
     * @return int|null
     */
    public function handle(JobRepository $jobRepository, QueueManager $manager)
    {
        if (! $this->confirmToProceed()) {
            return 1;
        }

        if (! method_exists(RedisQueue::class, 'clear')) {
            $this->components->error('Clearing queues is not supported on this version of Laravel.');

            return 1;
        }

        /** @var array<string, array<string, mixed>> $defaults */
        $defaults = (array) app('config')->get('horizon.defaults');

        $connection = $this->argument('connection')
            ?: Arr::first($defaults)['connection'] ?? 'redis';

        if (method_exists($jobRepository, 'purge')) {
            $jobRepository->purge($queue = $this->getQueue($connection));
        }

        $count = $manager->connection($connection)->clear($queue);

        $this->components->info('Cleared '.$count.' jobs from the ['.$queue.'] queue.');

        return 0;
    }

    /**
     * Get the queue name to clear.
     *
     * @param  string  $connection
     * @return string
     */
    protected function getQueue($connection)
    {
        return $this->option('queue') ?: app('config')->get(
            "queue.connections.{$connection}.queue",
            'default'
        );
    }
}
