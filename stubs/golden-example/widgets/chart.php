<?php

// PATTERN: ChartWidget for doughnut/bar/line charts.
// PATTERN: $heading is a non-static instance property (NOT protected static).
// PATTERN: getType() returns 'doughnut', 'bar', 'line', or 'pie'.

namespace Aicl\Filament\Widgets;

use Aicl\States\Active;
use Aicl\States\Archived;
use Aicl\States\Completed;
use Aicl\States\Draft;
use Aicl\States\OnHold;
use App\Models\Project;
use Filament\Widgets\ChartWidget;
use Livewire\Attributes\On;

class ProjectsByStatusChart extends ChartWidget
{
    // PATTERN: Non-static heading property.
    protected ?string $heading = 'Projects by Status';

    protected static ?int $sort = 2;

    protected ?string $pollingInterval = '60s';

    #[On('entity-changed')]
    public function onEntityChanged(): void
    {
        // PATTERN: updateChartData() refreshes the chart.
        $this->updateChartData();
    }

    // PATTERN: Return chart type as string.
    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getData(): array
    {
        // PATTERN: Map states to labels and colors for the chart.
        $statuses = [
            Draft::getMorphClass() => ['label' => 'Draft', 'color' => '#6b7280'],
            Active::getMorphClass() => ['label' => 'Active', 'color' => '#22c55e'],
            OnHold::getMorphClass() => ['label' => 'On Hold', 'color' => '#f59e0b'],
            Completed::getMorphClass() => ['label' => 'Completed', 'color' => '#3b82f6'],
            Archived::getMorphClass() => ['label' => 'Archived', 'color' => '#ef4444'],
        ];

        $counts = [];
        $labels = [];
        $colors = [];

        foreach ($statuses as $morphClass => $meta) {
            $count = Project::where('status', $morphClass)->count();
            $counts[] = $count;
            $labels[] = $meta['label'];
            $colors[] = $meta['color'];
        }

        // PATTERN: Chart.js data structure.
        return [
            'datasets' => [
                [
                    'data' => $counts,
                    'backgroundColor' => $colors,
                ],
            ],
            'labels' => $labels,
        ];
    }
}
