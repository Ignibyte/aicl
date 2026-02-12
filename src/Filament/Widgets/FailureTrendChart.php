<?php

namespace Aicl\Filament\Widgets;

use Aicl\Models\FailureReport;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;
use Livewire\Attributes\On;

class FailureTrendChart extends ChartWidget
{
    protected ?string $heading = 'Failure Reports Over Time';

    protected static ?int $sort = 1;

    protected ?string $pollingInterval = '60s';

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
        $months = collect(range(5, 0))->map(fn (int $i) => Carbon::now()->subMonths($i)->startOfMonth());

        $labels = $months->map(fn (Carbon $month) => $month->format('M Y'))->toArray();

        $counts = $months->map(function (Carbon $month) {
            return FailureReport::query()
                ->whereBetween('created_at', [$month, $month->copy()->endOfMonth()])
                ->count();
        })->toArray();

        return [
            'datasets' => [
                [
                    'label' => 'Reports',
                    'data' => $counts,
                    'borderColor' => '#3b82f6',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'fill' => true,
                    'tension' => 0.3,
                ],
            ],
            'labels' => $labels,
        ];
    }
}
