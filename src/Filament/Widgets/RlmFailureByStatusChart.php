<?php

namespace Aicl\Filament\Widgets;

use Aicl\Filament\Widgets\Traits\PausesWhenHidden;
use Aicl\Swoole\Cache\WidgetStatsCacheManager;
use Filament\Widgets\ChartWidget;
use Livewire\Attributes\On;

class RlmFailureByStatusChart extends ChartWidget
{
    use PausesWhenHidden;

    protected ?string $heading = 'Failures by Status';

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
        $statuses = [
            'reported' => ['label' => 'Reported', 'color' => '#6b7280'],
            'confirmed' => ['label' => 'Confirmed', 'color' => '#3b82f6'],
            'investigating' => ['label' => 'Investigating', 'color' => '#f59e0b'],
            'resolved' => ['label' => 'Resolved', 'color' => '#10b981'],
            'wont_fix' => ['label' => "Won't Fix", 'color' => '#8b5cf6'],
            'deprecated' => ['label' => 'Deprecated', 'color' => '#ef4444'],
        ];

        $counts = WidgetStatsCacheManager::getOrCompute(
            'failure_by_status',
            [WidgetStatsCacheManager::class, 'computeFailureByStatus'],
        );

        $data = [];
        $labels = [];
        $colors = [];

        foreach ($statuses as $value => $meta) {
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
