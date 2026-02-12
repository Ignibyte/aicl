<?php

namespace Aicl\Filament\Widgets;

use Aicl\Models\GenerationTrace;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Livewire\Attributes\On;

class ProjectHealthWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 4;

    protected ?string $pollingInterval = '60s';

    #[On('entity-changed')]
    public function entityChanged(): void
    {
        // Stats will refresh on next poll
    }

    protected function getStats(): array
    {
        $totalTraces = GenerationTrace::query()->count();
        $avgStructural = GenerationTrace::query()->avg('structural_score') ?? 0;
        $avgSemantic = GenerationTrace::query()->whereNotNull('semantic_score')->avg('semantic_score') ?? 0;
        $perfectScores = GenerationTrace::query()->where('structural_score', '>=', 100)->count();

        return [
            Stat::make('Total Generations', $totalTraces)
                ->description('Entities generated across all projects')
                ->color('primary'),
            Stat::make('Avg Structural Score', number_format((float) $avgStructural, 1))
                ->description('Average RLM structural validation')
                ->color($avgStructural >= 90 ? 'success' : ($avgStructural >= 70 ? 'warning' : 'danger')),
            Stat::make('Avg Semantic Score', number_format((float) $avgSemantic, 1))
                ->description('Average LLM-based cross-file validation')
                ->color($avgSemantic >= 90 ? 'success' : ($avgSemantic >= 70 ? 'warning' : 'info')),
            Stat::make('Perfect Scores', $perfectScores)
                ->description('100% structural validation')
                ->color('success'),
        ];
    }
}
