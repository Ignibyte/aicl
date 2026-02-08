<?php

namespace Aicl\Filament\Widgets;

use Aicl\Models\FailedJob;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Queue;

class QueueStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $failedCount = FailedJob::count();
        $lastFailed = FailedJob::latest('failed_at')->first();

        $pendingDefault = $this->getQueueSize('default');
        $pendingHigh = $this->getQueueSize('high');
        $pendingLow = $this->getQueueSize('low');
        $totalPending = $pendingDefault + $pendingHigh + $pendingLow;

        return [
            Stat::make('Pending Jobs', $totalPending)
                ->description($pendingHigh > 0 ? "{$pendingHigh} high priority" : 'Queue is processing')
                ->descriptionIcon($totalPending > 100 ? 'heroicon-o-exclamation-triangle' : 'heroicon-o-clock')
                ->color($totalPending > 100 ? 'warning' : ($totalPending > 0 ? 'primary' : 'success')),

            Stat::make('Failed Jobs', $failedCount)
                ->description($failedCount > 0 ? 'Requires attention' : 'All jobs successful')
                ->descriptionIcon($failedCount > 0 ? 'heroicon-o-exclamation-circle' : 'heroicon-o-check-circle')
                ->color($failedCount > 0 ? 'danger' : 'success'),

            Stat::make('Last Failure', $lastFailed?->failed_at?->diffForHumans() ?? 'Never')
                ->description($lastFailed?->job_name ?? 'No failures recorded')
                ->descriptionIcon('heroicon-o-clock')
                ->color($lastFailed && $lastFailed->failed_at->isToday() ? 'warning' : 'gray'),
        ];
    }

    protected function getQueueSize(string $queue): int
    {
        try {
            return Queue::size($queue);
        } catch (\Throwable) {
            return 0;
        }
    }
}
