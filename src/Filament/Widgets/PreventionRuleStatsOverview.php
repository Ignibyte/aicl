<?php

namespace Aicl\Filament\Widgets;

use Aicl\Filament\Widgets\Traits\PausesWhenHidden;
use Aicl\Swoole\Cache\WidgetStatsCacheManager;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Livewire\Attributes\On;

class PreventionRuleStatsOverview extends StatsOverviewWidget
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
            'prevention_rule_stats',
            [WidgetStatsCacheManager::class, 'computePreventionRuleStats'],
        );

        return [
            Stat::make('Total Rules', $data['total']),
            Stat::make('Active Rules', $data['active']),
            Stat::make('Avg Confidence', number_format($data['avg_confidence'], 2)),
            Stat::make('Total Applied', $data['total_applied']),
        ];
    }
}
