<?php

declare(strict_types=1);

namespace Aicl\Horizon\Console;

use Aicl\Horizon\Contracts\JobRepository;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * Deletes one or all failed jobs from the Horizon failed job repository.
 *
 * @codeCoverageIgnore Horizon process management
 */
#[AsCommand(name: 'aicl:horizon:forget')]
/**
 * ForgetFailedCommand.
 */
class ForgetFailedCommand extends Command
{
    /**
     * The console command signature.
     *
     * @var string
     */
    protected $signature = 'aicl:horizon:forget {id? : The ID of the failed job} {--all : Delete all failed jobs}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete a failed queue job';

    /**
     * Execute the console command.
     *
     * @return int|null
     */
    public function handle(JobRepository $repository)
    {
        if ($this->option('all')) {
            $totalFailedCount = $repository->totalFailed();

            do {
                $failedJobs = collect($repository->getFailed());

                $failedJobs->pluck('id')->each(function ($failedId) use ($repository): void {
                    $repository->deleteFailed($failedId);

                    if (app('queue.failer')->forget($failedId)) {
                        $this->components->info('Failed job (id): '.$failedId.' deleted successfully!');
                    }
                });
            } while ($repository->totalFailed() !== 0 && $failedJobs->isNotEmpty());

            if ($totalFailedCount) {
                $this->components->info($totalFailedCount.' failed jobs deleted successfully!');
            } else {
                $this->components->info('No failed jobs detected.');
            }

            return;
        }

        if (! $this->argument('id')) {
            $this->components->error('No failed job ID provided.');
        }

        $id = (string) $this->argument('id');

        $repository->deleteFailed($id);

        if (app('queue.failer')->forget($id)) {
            $this->components->info('Failed job deleted successfully!');
        } else {
            $this->components->error('No failed job matches the given ID.');

            return 1;
        }
    }
}
