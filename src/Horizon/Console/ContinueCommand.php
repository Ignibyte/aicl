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
 * Instructs the Horizon master supervisor to resume processing jobs.
 *
 * @codeCoverageIgnore Horizon process management
 */
#[AsCommand(name: 'aicl:horizon:continue')]
/**
 * ContinueCommand.
 */
class ContinueCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'aicl:horizon:continue';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Instruct the master supervisor to continue processing jobs';

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
                $this->components->info('Sending CONT signal to processes.');

                return $collection;
            })
            ->whenEmpty(function ($collection) {
                $this->components->info('No processes to continue.');

                return $collection;
            })
            ->each(function ($processId) {
                $result = true;

                $this->components->task("Process: $processId", function () use ($processId, &$result) {
                    return $result = posix_kill($processId, SIGCONT);
                });

                if (! $result) {
                    $this->components->error("Failed to kill process: {$processId} (".posix_strerror(posix_get_last_error()).')');
                }
            })->whenNotEmpty(fn () => $this->output->writeln(''));
    }
}
