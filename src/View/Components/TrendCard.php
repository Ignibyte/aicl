<?php

declare(strict_types=1);

namespace Aicl\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * Stat card with sparkline trend visualization.
 *
 * AI Decision Rules:
 * - Use when entity has created_at and user wants trends over time
 * - Provide 7-30 data points for the sparkline
 * - Use alongside StatCards for detailed metric sections
 *
 * @codeCoverageIgnore Blade view component
 */
class TrendCard extends Component
{
    /**
     * @param  array<int, int|float>  $data
     */
    public function __construct(
        public string $label,
        public string|int|float $value,
        public array $data = [],
        public string $color = 'primary',
        public ?string $description = null,
    ) {}

    public function sparklineClass(): string
    {
        return match ($this->color) {
            'primary' => 'text-primary-500',
            'success', 'green' => 'text-green-500',
            'warning', 'yellow' => 'text-yellow-500',
            'danger', 'red' => 'text-red-500',
            'info', 'blue' => 'text-blue-500',
            default => 'text-gray-500',
        };
    }

    public function sparklinePath(): string
    {
        if (empty($this->data)) {
            return '';
        }

        $max = max($this->data) ?: 1;
        $min = min($this->data);
        $range = $max - $min ?: 1;
        $count = count($this->data);
        $width = 100;
        $height = 30;

        $points = [];
        foreach ($this->data as $i => $val) {
            $x = ($i / max($count - 1, 1)) * $width;
            $y = $height - (($val - $min) / $range) * $height;
            $points[] = round($x, 1).','.round($y, 1);
        }

        return 'M '.implode(' L ', $points);
    }

    public function render(): View
    {
        return view('aicl::components.trend-card');
    }
}
