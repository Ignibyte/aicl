<?php

namespace Aicl\Horizon\Console;

use Aicl\Horizon\Contracts\MetricsRepository;
use Aicl\Horizon\Lock;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'aicl:horizon:snapshot')]
class SnapshotCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'aicl:horizon:snapshot';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Store a snapshot of the queue metrics';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle(Lock $lock, MetricsRepository $metrics)
    {
        if ($lock->get('metrics:snapshot', config('aicl-horizon.metrics.snapshot_lock', 300) - 30)) {
            $metrics->snapshot();

            $this->components->info('Metrics snapshot stored successfully.');
        }
    }
}
