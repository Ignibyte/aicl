<?php

namespace Aicl\Filament\Widgets;

use Aicl\Models\GenerationTrace;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Livewire\Attributes\On;

class GenerationTraceStatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $pollingInterval = '60s';

    #[On('entity-changed')]
    public function entityChanged(): void
    {
        // Stats will refresh on next poll
    }

    protected function getStats(): array
    {
        $total = GenerationTrace::query()->count();
        $avgStructural = GenerationTrace::query()->whereNotNull('structural_score')->avg('structural_score');
        $avgSemantic = GenerationTrace::query()->whereNotNull('semantic_score')->avg('semantic_score');
        $avgFixIterations = GenerationTrace::query()->avg('fix_iterations');

        return [
            Stat::make('Total Traces', $total),
            Stat::make('Avg Structural Score', $avgStructural ? number_format((float) $avgStructural, 1).'%' : '—'),
            Stat::make('Avg Semantic Score', $avgSemantic ? number_format((float) $avgSemantic, 1).'%' : '—'),
            Stat::make('Avg Fix Iterations', $avgFixIterations !== null ? number_format((float) $avgFixIterations, 1) : '0'),
        ];
    }
}
