<?php

namespace Aicl\Filament\Widgets;

use Aicl\Models\RlmPattern;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Livewire\Attributes\On;

class RlmPatternStatsOverview extends StatsOverviewWidget
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
        $total = RlmPattern::query()->count();
        $active = RlmPattern::query()->where('is_active', true)->count();
        $inactive = $total - $active;

        $evaluated = RlmPattern::query()
            ->whereNotNull('last_evaluated_at')
            ->selectRaw('SUM(pass_count) as total_pass, SUM(pass_count + fail_count) as total_eval')
            ->first();

        $avgPassRate = ($evaluated->total_eval > 0)
            ? round(($evaluated->total_pass / $evaluated->total_eval) * 100, 1).'%'
            : 'N/A';

        return [
            Stat::make('Total Patterns', $total),
            Stat::make('Active', $active)
                ->color('success'),
            Stat::make('Inactive', $inactive)
                ->color('danger'),
            Stat::make('Avg Pass Rate', $avgPassRate)
                ->color('info'),
        ];
    }
}
