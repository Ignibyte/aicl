<?php

namespace Aicl\Filament\Widgets;

use Aicl\Models\FailureReport;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Livewire\Attributes\On;

class FailureReportStatsOverview extends StatsOverviewWidget
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
        $total = FailureReport::query()->count();
        $resolved = FailureReport::query()->resolved()->count();
        $resolvedPercent = $total > 0 ? round(($resolved / $total) * 100, 1) : 0;
        $avgTimeToResolve = FailureReport::query()->resolved()
            ->whereNotNull('time_to_resolve')
            ->avg('time_to_resolve');

        return [
            Stat::make('Total Reports', $total),
            Stat::make('Resolved', $resolved)
                ->description("{$resolvedPercent}% resolution rate")
                ->color($resolvedPercent >= 80 ? 'success' : ($resolvedPercent >= 50 ? 'warning' : 'danger')),
            Stat::make('Unresolved', FailureReport::query()->unresolved()->count())
                ->color('danger'),
            Stat::make('Avg Time to Resolve', $avgTimeToResolve ? round($avgTimeToResolve).' min' : 'N/A'),
        ];
    }
}
