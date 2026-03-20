<?php

namespace Aicl\Horizon\Console;

use Aicl\Horizon\Models\QueueMetricSnapshot;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'aicl:horizon:purge-metrics')]
class PurgeMetricsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'aicl:horizon:purge-metrics
                            {--days= : Number of days of data to retain}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Purge queue metric snapshots older than the retention period';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = (int) ($this->option('days') ?? config('aicl-horizon.metrics.retention_days', 30));

        $deleted = QueueMetricSnapshot::query()
            ->where('recorded_at', '<', now()->subDays($days))
            ->delete();

        $this->components->info(
            sprintf('Purged %s metric %s older than %d %s.',
                number_format($deleted),
                $deleted === 1 ? 'snapshot' : 'snapshots',
                $days,
                $days === 1 ? 'day' : 'days',
            )
        );

        return self::SUCCESS;
    }
}
