<?php

namespace Aicl\Filament\Widgets;

use Aicl\Filament\Widgets\Traits\PausesWhenHidden;
use Aicl\Swoole\Cache\WidgetStatsCacheManager;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Livewire\Attributes\On;

class RlmFailureStatsOverview extends StatsOverviewWidget
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
            'rlm_failure_stats',
            [WidgetStatsCacheManager::class, 'computeRlmFailureStats'],
        );

        return [
            Stat::make('Total Failures', $data['total'])
                ->description('All tracked failures')
                ->color('primary'),
            Stat::make('Critical', $data['critical'])
                ->description('Requires immediate attention')
                ->color('danger'),
            Stat::make('High Severity', $data['high'])
                ->description('High priority failures')
                ->color('warning'),
            Stat::make('Promotable', $data['promotable'])
                ->description('Eligible for base promotion')
                ->color('info'),
        ];
    }
}
