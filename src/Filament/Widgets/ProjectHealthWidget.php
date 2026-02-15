<?php

namespace Aicl\Filament\Widgets;

use Aicl\Filament\Widgets\Traits\PausesWhenHidden;
use Aicl\Swoole\Cache\WidgetStatsCacheManager;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Livewire\Attributes\On;

class ProjectHealthWidget extends StatsOverviewWidget
{
    use PausesWhenHidden;

    protected static ?int $sort = 4;

    #[On('entity-changed')]
    public function entityChanged(): void
    {
        // Stats will refresh on next poll
    }

    protected function getStats(): array
    {
        $data = WidgetStatsCacheManager::getOrCompute(
            'project_health',
            [WidgetStatsCacheManager::class, 'computeProjectHealth'],
        );

        $avgStructural = $data['avg_structural'];
        $avgSemantic = $data['avg_semantic'];

        return [
            Stat::make('Total Generations', $data['total'])
                ->description('Entities generated across all projects')
                ->color('primary'),
            Stat::make('Avg Structural Score', number_format((float) $avgStructural, 1))
                ->description('Average RLM structural validation')
                ->color($avgStructural >= 90 ? 'success' : ($avgStructural >= 70 ? 'warning' : 'danger')),
            Stat::make('Avg Semantic Score', number_format((float) $avgSemantic, 1))
                ->description('Average LLM-based cross-file validation')
                ->color($avgSemantic >= 90 ? 'success' : ($avgSemantic >= 70 ? 'warning' : 'info')),
            Stat::make('Perfect Scores', $data['perfect_scores'])
                ->description('100% structural validation')
                ->color('success'),
        ];
    }
}
