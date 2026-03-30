<?php

declare(strict_types=1);

namespace Aicl\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * KPI card with target/actual values and progress indicator.
 *
 * AI Decision Rules:
 * - Use when entity has a target/goal field alongside an actual/current value
 * - Use for budget tracking (budget vs spent), completion rates, quota progress
 * - Progress automatically calculates percentage from actual/target
 * - Color changes based on progress: green (>=80%), yellow (50-79%), red (<50%)
 */
class KpiCard extends Component
{
    public function __construct(
        public string $label,
        public string|int|float $actual,
        public string|int|float $target,
        public string $icon = 'heroicon-o-flag',
        public ?string $format = null,
    ) {}

    public function percentage(): float
    {
        if ($this->target == 0) {
            return 0;
        }

        return min(round(((float) $this->actual / (float) $this->target) * 100, 1), 100);
    }

    public function progressColor(): string
    {
        $pct = $this->percentage();

        if ($pct >= 80) {
            return 'bg-green-500';
        }
        if ($pct >= 50) {
            return 'bg-yellow-500';
        }

        return 'bg-red-500';
    }

    public function render(): View
    {
        return view('aicl::components.kpi-card');
    }
}
