<?php

declare(strict_types=1);

namespace Aicl\Horizon\Console;

use Aicl\Horizon\Contracts\MetricsRepository;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * @codeCoverageIgnore Horizon process management
 */
#[AsCommand(name: 'aicl:horizon:clear-metrics')]
class ClearMetricsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'aicl:horizon:clear-metrics';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete metrics for all jobs and queues';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle(MetricsRepository $metrics)
    {
        $metrics->clear();

        $this->components->info('Metrics cleared successfully.');
    }
}
