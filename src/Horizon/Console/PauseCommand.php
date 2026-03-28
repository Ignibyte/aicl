<?php

declare(strict_types=1);

namespace Aicl\Horizon\Console;

use Aicl\Horizon\Contracts\MasterSupervisorRepository;
use Aicl\Horizon\MasterSupervisor;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * Pauses the Horizon master supervisor to stop processing jobs.
 *
 * @codeCoverageIgnore Horizon process management
 */
#[AsCommand(name: 'aicl:horizon:pause')]
/**
 * PauseCommand.
 */
class PauseCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'aicl:horizon:pause';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Pause the master supervisor';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle(MasterSupervisorRepository $masters)
    {
        $masters = collect($masters->all())
            ->filter(fn ($master) => Str::startsWith($master->name, MasterSupervisor::basename()))
            ->all();

        collect(Arr::pluck($masters, 'pid'))
            ->whenNotEmpty(function ($collection) {
                $this->components->info('Sending USR2 signal to processes.');

                return $collection;
            })
            ->whenEmpty(function ($collection) {
                $this->components->info('No processes to pause.');

                return $collection;
            })
            ->each(function ($processId) {
                $result = true;

                $this->components->task("Process: $processId", function () use ($processId, &$result) {
                    return $result = posix_kill($processId, SIGUSR2);
                });

                if (! $result) {
                    $this->components->error("Failed to kill process: {$processId} (".posix_strerror(posix_get_last_error()).')');
                }
            })->whenNotEmpty(fn () => $this->output->writeln(''));
    }
}
