<?php

namespace Aicl\Filament\Widgets;

use Aicl\Models\RlmFailure;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Livewire\Attributes\On;

class RlmFailureStatsOverview extends StatsOverviewWidget
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
        $total = RlmFailure::query()->count();
        $critical = RlmFailure::query()->where('severity', 'critical')->count();
        $high = RlmFailure::query()->where('severity', 'high')->count();
        $promotable = RlmFailure::query()->promotable()->count();

        return [
            Stat::make('Total Failures', $total)
                ->description('All tracked failures')
                ->color('primary'),
            Stat::make('Critical', $critical)
                ->description('Requires immediate attention')
                ->color('danger'),
            Stat::make('High Severity', $high)
                ->description('High priority failures')
                ->color('warning'),
            Stat::make('Promotable', $promotable)
                ->description('Eligible for base promotion')
                ->color('info'),
        ];
    }
}
