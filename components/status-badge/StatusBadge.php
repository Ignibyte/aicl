<?php

namespace Aicl\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * Colored badge for status/enum values.
 *
 * AI Decision Rules:
 * - Use for any status, state, or category field in display contexts
 * - Color should match the enum/state's defined color
 * - Use inside tables, metadata lists, and card headers
 *
 * @example <x-aicl-status-badge label="Active" color="success" />
 */
class StatusBadge extends Component
{
    public function __construct(
        public string $label,
        public string $color = 'gray',
        public ?string $icon = null,
    ) {}

    public function colorClasses(): string
    {
        return match ($this->color) {
            'primary' => 'bg-primary-50 text-primary-700 ring-primary-600/20 dark:bg-primary-400/10 dark:text-primary-400 dark:ring-primary-400/30',
            'success', 'green' => 'bg-green-50 text-green-700 ring-green-600/20 dark:bg-green-400/10 dark:text-green-400 dark:ring-green-400/30',
            'warning', 'yellow' => 'bg-yellow-50 text-yellow-700 ring-yellow-600/20 dark:bg-yellow-400/10 dark:text-yellow-400 dark:ring-yellow-400/30',
            'danger', 'red' => 'bg-red-50 text-red-700 ring-red-600/20 dark:bg-red-400/10 dark:text-red-400 dark:ring-red-400/30',
            'info', 'blue' => 'bg-blue-50 text-blue-700 ring-blue-600/20 dark:bg-blue-400/10 dark:text-blue-400 dark:ring-blue-400/30',
            default => 'bg-gray-50 text-gray-700 ring-gray-600/20 dark:bg-gray-400/10 dark:text-gray-400 dark:ring-gray-400/30',
        };
    }

    public function render(): View
    {
        return view('aicl::components.status-badge');
    }
}
