<?php

namespace Aicl\Filament\Widgets;

use Aicl\Filament\Widgets\Traits\PausesWhenHidden;
use Aicl\Swoole\Cache\WidgetStatsCacheManager;
use Filament\Widgets\ChartWidget;
use Livewire\Attributes\On;

class FailureTrendChart extends ChartWidget
{
    use PausesWhenHidden;

    protected ?string $heading = 'Failure Reports Over Time';

    protected static ?int $sort = 1;

    #[On('entity-changed')]
    public function entityChanged(): void
    {
        $this->updateChartData();
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $data = WidgetStatsCacheManager::getOrCompute(
            'failure_trend',
            [WidgetStatsCacheManager::class, 'computeFailureTrend'],
        );

        return [
            'datasets' => [
                [
                    'label' => 'Reports',
                    'data' => $data['counts'],
                    'borderColor' => '#3b82f6',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'fill' => true,
                    'tension' => 0.3,
                ],
            ],
            'labels' => $data['labels'],
        ];
    }
}
