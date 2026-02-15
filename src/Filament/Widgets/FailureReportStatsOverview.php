<?php

namespace Aicl\Filament\Widgets;

use Aicl\Filament\Widgets\Traits\PausesWhenHidden;
use Aicl\Swoole\Cache\WidgetStatsCacheManager;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Livewire\Attributes\On;

class FailureReportStatsOverview extends StatsOverviewWidget
{
    use PausesWhenHidden;

    protected static ?int $sort = 1;

    #[On('entity-changed')]
    public function entityChanged(): void
    {
        // Stats will refresh on next poll
    }

    protected function getStats(): array
    {
        $data = WidgetStatsCacheManager::getOrCompute(
            'failure_report_stats',
            [WidgetStatsCacheManager::class, 'computeFailureReportStats'],
        );

        $resolvedPercent = $data['total'] > 0 ? round(($data['resolved'] / $data['total']) * 100, 1) : 0;

        return [
            Stat::make('Total Reports', $data['total']),
            Stat::make('Resolved', $data['resolved'])
                ->description("{$resolvedPercent}% resolution rate")
                ->color($resolvedPercent >= 80 ? 'success' : ($resolvedPercent >= 50 ? 'warning' : 'danger')),
            Stat::make('Unresolved', $data['unresolved'])
                ->color('danger'),
            Stat::make('Avg Time to Resolve', $data['avg_time_to_resolve'] ? round($data['avg_time_to_resolve']).' min' : 'N/A'),
        ];
    }
}
