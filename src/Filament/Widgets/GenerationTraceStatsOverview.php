<?php

namespace Aicl\Filament\Widgets;

use Aicl\Filament\Widgets\Traits\PausesWhenHidden;
use Aicl\Swoole\Cache\WidgetStatsCacheManager;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Livewire\Attributes\On;

class GenerationTraceStatsOverview extends StatsOverviewWidget
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
            'generation_trace_stats',
            [WidgetStatsCacheManager::class, 'computeGenerationTraceStats'],
        );

        return [
            Stat::make('Total Traces', $data['total']),
            Stat::make('Avg Structural Score', $data['avg_structural'] ? number_format($data['avg_structural'], 1).'%' : '—'),
            Stat::make('Avg Semantic Score', $data['avg_semantic'] ? number_format($data['avg_semantic'], 1).'%' : '—'),
            Stat::make('Avg Fix Iterations', number_format($data['avg_fix_iterations'], 1)),
        ];
    }
}
