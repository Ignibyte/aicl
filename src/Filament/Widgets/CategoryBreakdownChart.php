<?php

namespace Aicl\Filament\Widgets;

use Aicl\Filament\Widgets\Traits\PausesWhenHidden;
use Aicl\Swoole\Cache\WidgetStatsCacheManager;
use Filament\Widgets\ChartWidget;
use Livewire\Attributes\On;

class CategoryBreakdownChart extends ChartWidget
{
    use PausesWhenHidden;

    protected ?string $heading = 'Failures by Category';

    protected static ?int $sort = 2;

    #[On('entity-changed')]
    public function entityChanged(): void
    {
        $this->updateChartData();
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getData(): array
    {
        $categories = [
            'scaffolding' => ['label' => 'Scaffolding', 'color' => '#3b82f6'],
            'testing' => ['label' => 'Testing', 'color' => '#10b981'],
            'naming' => ['label' => 'Naming', 'color' => '#f59e0b'],
            'filament' => ['label' => 'Filament', 'color' => '#8b5cf6'],
            'database' => ['label' => 'Database', 'color' => '#ef4444'],
            'security' => ['label' => 'Security', 'color' => '#ec4899'],
            'performance' => ['label' => 'Performance', 'color' => '#06b6d4'],
        ];

        $counts = WidgetStatsCacheManager::getOrCompute(
            'failure_by_category',
            [WidgetStatsCacheManager::class, 'computeFailureByCategory'],
        );

        $data = [];
        $labels = [];
        $colors = [];

        foreach ($categories as $value => $meta) {
            $count = $counts[$value] ?? 0;
            if ($count > 0) {
                $data[] = $count;
                $labels[] = $meta['label'];
                $colors[] = $meta['color'];
            }
        }

        if (empty($data)) {
            $data = [0];
            $labels = ['No Data'];
            $colors = ['#d1d5db'];
        }

        return [
            'datasets' => [
                [
                    'data' => $data,
                    'backgroundColor' => $colors,
                ],
            ],
            'labels' => $labels,
        ];
    }
}
