<?php

// PATTERN: StatsOverviewWidget shows KPI cards (counts, totals).
// PATTERN: Uses pollingInterval for auto-refresh.
// PATTERN: Listens for 'entity-changed' event to refresh on WebSocket updates.

namespace Aicl\Filament\Widgets;

use Aicl\States\Active;
use Aicl\States\Completed;
use App\Models\Project;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Livewire\Attributes\On;

class ProjectStatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $pollingInterval = '60s';

    // PATTERN: Listen for broadcast events to refresh widget data.
    #[On('entity-changed')]
    public function onEntityChanged(): void {}

    protected function getStats(): array
    {
        $total = Project::count();
        $active = Project::where('status', Active::getMorphClass())->count();
        $completed = Project::where('status', Completed::getMorphClass())->count();
        $overdue = Project::where('status', Active::getMorphClass())
            ->where('end_date', '<', now())
            ->count();

        return [
            Stat::make('Total Projects', $total)
                ->description('All projects')
                ->color('primary'),
            Stat::make('Active', $active)
                ->description('Currently in progress')
                ->color('success'),
            Stat::make('Completed', $completed)
                ->description('Successfully finished')
                ->color('info'),
            // PATTERN: Conditional color based on value.
            Stat::make('Overdue', $overdue)
                ->description('Past deadline')
                ->color($overdue > 0 ? 'danger' : 'success'),
        ];
    }
}
