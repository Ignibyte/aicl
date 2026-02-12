<?php

namespace Aicl\Filament\Widgets;

use Aicl\Models\PreventionRule;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Livewire\Attributes\On;

class PreventionRuleStatsOverview extends StatsOverviewWidget
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
        return [
            Stat::make('Total Rules', PreventionRule::query()->count()),
            Stat::make('Active Rules', PreventionRule::query()->where('is_active', true)->count()),
            Stat::make('Avg Confidence', number_format((float) PreventionRule::query()->where('is_active', true)->avg('confidence'), 2)),
            Stat::make('Total Applied', PreventionRule::query()->sum('applied_count')),
        ];
    }
}
