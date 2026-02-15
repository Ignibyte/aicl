<?php

namespace Aicl\Filament\Widgets;

use Aicl\Filament\Widgets\Traits\PausesWhenHidden;
use Aicl\Swoole\Cache\WidgetStatsCacheManager;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Livewire\Attributes\On;

class RlmPatternStatsOverview extends StatsOverviewWidget
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
            'rlm_pattern_stats',
            [WidgetStatsCacheManager::class, 'computeRlmPatternStats'],
        );

        $inactive = $data['total'] - $data['active'];
        $avgPassRate = ($data['total_eval'] > 0)
            ? round(($data['total_pass'] / $data['total_eval']) * 100, 1).'%'
            : 'N/A';

        return [
            Stat::make('Total Patterns', $data['total']),
            Stat::make('Active', $data['active'])
                ->color('success'),
            Stat::make('Inactive', $inactive)
                ->color('danger'),
            Stat::make('Avg Pass Rate', $avgPassRate)
                ->color('info'),
        ];
    }
}
