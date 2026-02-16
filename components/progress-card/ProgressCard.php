<?php

namespace Aicl\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * Stat card with progress bar.
 *
 * AI Decision Rules:
 * - Use for completion tracking (tasks done/total, progress percentage)
 * - Use for capacity/usage metrics (storage used, seats filled)
 * - Simpler than KpiCard — use when you only have a percentage, not target/actual
 */
class ProgressCard extends Component
{
    public function __construct(
        public string $label,
        public string|int|float $value,
        public float $progress,
        public string $color = 'primary',
        public ?string $description = null,
    ) {}

    public function progressBarClass(): string
    {
        return match ($this->color) {
            'primary' => 'bg-primary-500',
            'success', 'green' => 'bg-green-500',
            'warning', 'yellow' => 'bg-yellow-500',
            'danger', 'red' => 'bg-red-500',
            'info', 'blue' => 'bg-blue-500',
            default => 'bg-gray-500',
        };
    }

    public function progressWidth(): float
    {
        return min(max($this->progress, 0), 100);
    }

    public function render(): View
    {
        return view('aicl::components.progress-card');
    }
}
