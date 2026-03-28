<?php

declare(strict_types=1);

namespace Aicl\Horizon\Console;

use Aicl\Horizon\Contracts\MasterSupervisorRepository;
use Aicl\Horizon\MasterSupervisor;
use Illuminate\Console\Command;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Support\Arr;
use Illuminate\Support\InteractsWithTime;
use Illuminate\Support\Str;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * Gracefully terminates all Horizon master supervisors and optionally waits for completion.
 *
 * @codeCoverageIgnore Horizon process management
 */
#[AsCommand(name: 'aicl:horizon:terminate')]
/**
 * TerminateCommand.
 */
class TerminateCommand extends Command
{
    use InteractsWithTime;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'aicl:horizon:terminate
                            {--wait : Wait for all workers to terminate}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Terminate the master supervisor so it can be restarted';

    /**
     * Execute the console command.
     *
     * @return int|null
     */
    public function handle(CacheFactory $cache, MasterSupervisorRepository $masters)
    {
        if (config('aicl-horizon.fast_termination')) {
            $cache->forever(
                'aicl:horizon:terminate:wait', $this->option('wait')
            );
        }

        $masters = collect($masters->all())
            ->filter(fn ($master) => Str::startsWith($master->name, MasterSupervisor::basename()))
            ->all();

        $exitCode = null;

        $result = collect(Arr::pluck($masters, 'pid'))
            ->whenNotEmpty(function ($collection) {
                $this->components->info('Sending TERM signal to processes.');

                return $collection;
            })
            ->whenEmpty(function ($collection) use (&$exitCode) {
                $this->components->info('No processes to terminate.');

                $exitCode = Command::FAILURE;

                return $collection;
            })
            ->each(function ($processId) use (&$exitCode) {
                $result = true;

                $this->components->task("Process: $processId", function () use ($processId, &$result) {
                    return $result = posix_kill($processId, SIGTERM);
                });

                if (! $result) {
                    $this->components->error("Failed to kill process: {$processId} (".posix_strerror(posix_get_last_error()).')');

                    $exitCode = Command::FAILURE;
                } elseif ($exitCode === null) {
                    $exitCode = Command::SUCCESS;
                }
            })->whenNotEmpty(fn () => $this->output->writeln(''));

        app('cache')->forever('illuminate:queue:restart', $this->currentTime());

        return $exitCode ?? Command::SUCCESS;
    }
}
