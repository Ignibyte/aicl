<?php

namespace Aicl\Filament\Widgets;

use Aicl\Horizon\Contracts\MetricsRepository;
use Aicl\Models\FailedJob;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;

class QueueStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    /** Cache TTL for queue stats (seconds). */
    private const CACHE_TTL = 30;

    protected function getStats(): array
    {
        $cached = Cache::remember('aicl:widget:queue-stats', self::CACHE_TTL, function (): array {
            $failedCount = FailedJob::count();
            $lastFailed = FailedJob::latest('failed_at')->first();

            return [
                'failed_count' => $failedCount,
                'last_failed_at' => $lastFailed?->failed_at?->toIso8601String(),
                'last_failed_name' => $lastFailed?->job_name,
                'last_failed_today' => $lastFailed?->failed_at?->isToday() ?? false,
            ];
        });

        // Queue sizes are cheap Redis calls — no caching needed
        $pendingDefault = $this->getQueueSize('default');
        $pendingHigh = $this->getQueueSize('high');
        $pendingLow = $this->getQueueSize('low');
        $totalPending = $pendingDefault + $pendingHigh + $pendingLow;

        $failedCount = $cached['failed_count'];
        $lastFailedAt = $cached['last_failed_at']
            ? Carbon::parse($cached['last_failed_at'])->diffForHumans()
            : 'Never';

        $stats = [
            Stat::make('Pending Jobs', $totalPending)
                ->description($pendingHigh > 0 ? "{$pendingHigh} high priority" : 'Queue is processing')
                ->descriptionIcon($totalPending > 100 ? 'heroicon-o-exclamation-triangle' : 'heroicon-o-clock')
                ->color($totalPending > 100 ? 'warning' : ($totalPending > 0 ? 'primary' : 'success')),

            Stat::make('Failed Jobs', $failedCount)
                ->description($failedCount > 0 ? 'Requires attention' : 'All jobs successful')
                ->descriptionIcon($failedCount > 0 ? 'heroicon-o-exclamation-circle' : 'heroicon-o-check-circle')
                ->color($failedCount > 0 ? 'danger' : 'success'),

            Stat::make('Last Failure', $lastFailedAt)
                ->description($cached['last_failed_name'] ?? 'No failures recorded')
                ->descriptionIcon('heroicon-o-clock')
                ->color($cached['last_failed_today'] ? 'warning' : 'gray'),
        ];

        // Add Horizon metrics when available
        if (config('aicl.features.horizon', true) && app()->bound(MetricsRepository::class)) {
            $metrics = app(MetricsRepository::class);

            $jobsPerMinute = $this->getJobsPerMinute($metrics);
            $stats[] = Stat::make('Jobs / Min', number_format($jobsPerMinute, 1))
                ->description('Throughput')
                ->descriptionIcon('heroicon-o-bolt')
                ->color($jobsPerMinute > 0 ? 'success' : 'gray');
        }

        return $stats;
    }

    protected function getQueueSize(string $queue): int
    {
        try {
            return Queue::size($queue);
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * Calculate approximate jobs per minute from Horizon metrics snapshots.
     */
    protected function getJobsPerMinute(MetricsRepository $metrics): float
    {
        $snapshots = $metrics->snapshotsForQueue('default');

        if (empty($snapshots)) {
            return 0.0;
        }

        $latest = end($snapshots);

        return (float) ($latest->throughput ?? 0);
    }
}
