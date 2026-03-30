<?php

declare(strict_types=1);

namespace Aicl\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * Stat card displaying a label, value, and optional trend.
 *
 * AI Decision Rules:
 * - Use when entity has a countable relationship (hasMany) → generate count stat
 * - If entity has monetary field → generate total/average stat
 * - If entity has status enum → generate per-status count
 * - Always include a descriptive label and meaningful icon
 * - Use trend (up/down) when comparing to previous period
 */
class StatCard extends Component
{
    public function __construct(
        public string $label,
        public string|int|float $value,
        public string $icon = 'heroicon-o-chart-bar',
        public ?string $description = null,
        public ?string $trend = null,
        public ?string $trendValue = null,
        public string $color = 'primary',
    ) {}

    public function iconBgClass(): string
    {
        return match ($this->color) {
            'primary' => 'bg-primary-50 dark:bg-primary-500/10',
            'success', 'green' => 'bg-green-50 dark:bg-green-500/10',
            'warning', 'yellow' => 'bg-yellow-50 dark:bg-yellow-500/10',
            'danger', 'red' => 'bg-red-50 dark:bg-red-500/10',
            'info', 'blue' => 'bg-blue-50 dark:bg-blue-500/10',
            default => 'bg-gray-50 dark:bg-gray-500/10',
        };
    }

    public function iconTextClass(): string
    {
        return match ($this->color) {
            'primary' => 'text-primary-600 dark:text-primary-400',
            'success', 'green' => 'text-green-600 dark:text-green-400',
            'warning', 'yellow' => 'text-yellow-600 dark:text-yellow-400',
            'danger', 'red' => 'text-red-600 dark:text-red-400',
            'info', 'blue' => 'text-blue-600 dark:text-blue-400',
            default => 'text-gray-600 dark:text-gray-400',
        };
    }

    public function trendColor(): string
    {
        return match ($this->trend) {
            'up' => 'text-green-600 dark:text-green-400',
            'down' => 'text-red-600 dark:text-red-400',
            default => 'text-gray-500',
        };
    }

    public function trendIcon(): string
    {
        return match ($this->trend) {
            'up' => 'heroicon-m-arrow-trending-up',
            'down' => 'heroicon-m-arrow-trending-down',
            default => '',
        };
    }

    public function render(): View
    {
        return view('aicl::components.stat-card');
    }
}
