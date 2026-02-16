<?php

namespace Aicl\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * Badge with color variants, dot indicator, icons, and optional remove button.
 *
 * AI Decision Rules:
 * - Use for tags, labels, counts, and inline status indicators
 * - For entity status fields, prefer <x-aicl-status-badge> instead
 * - Variant 'soft' (default) for inline text badges, 'solid' for high-emphasis, 'outline' for minimal
 * - Use 'dot' for connection status indicators
 * - Use 'removable' for tag/filter chips that can be dismissed
 */
class Badge extends Component
{
    public function __construct(
        public string $label,
        public string $color = 'gray',
        public string $variant = 'soft',
        public string $size = 'default',
        public string $shape = 'full',
        public bool $dot = false,
        public bool $removable = false,
        public ?string $icon = null,
    ) {}

    public function sizeClasses(): string
    {
        return match ($this->size) {
            'sm' => 'px-2 py-0.5 text-[11px]',
            'lg' => 'px-3 py-1 text-xs',
            default => 'px-2.5 py-0.5 text-xs',
        };
    }

    public function shapeClass(): string
    {
        return $this->shape === 'md' ? 'rounded-md' : 'rounded-full';
    }

    public function colorClasses(): string
    {
        return match ($this->variant) {
            'solid' => $this->solidClasses(),
            'outline' => $this->outlineClasses(),
            default => $this->softClasses(),
        };
    }

    private function softClasses(): string
    {
        return match ($this->color) {
            'primary' => 'bg-primary-50 text-primary-700 ring-1 ring-inset ring-primary-600/20 dark:bg-primary-400/10 dark:text-primary-400 dark:ring-primary-400/30',
            'success', 'green' => 'bg-green-50 text-green-700 ring-1 ring-inset ring-green-600/20 dark:bg-green-400/10 dark:text-green-400 dark:ring-green-400/30',
            'warning', 'yellow' => 'bg-yellow-50 text-yellow-700 ring-1 ring-inset ring-yellow-600/20 dark:bg-yellow-400/10 dark:text-yellow-400 dark:ring-yellow-400/30',
            'danger', 'red' => 'bg-red-50 text-red-700 ring-1 ring-inset ring-red-600/20 dark:bg-red-400/10 dark:text-red-400 dark:ring-red-400/30',
            'info', 'blue' => 'bg-blue-50 text-blue-700 ring-1 ring-inset ring-blue-600/20 dark:bg-blue-400/10 dark:text-blue-400 dark:ring-blue-400/30',
            default => 'bg-gray-50 text-gray-700 ring-1 ring-inset ring-gray-600/20 dark:bg-gray-400/10 dark:text-gray-400 dark:ring-gray-400/30',
        };
    }

    private function solidClasses(): string
    {
        return match ($this->color) {
            'primary' => 'bg-primary-500 text-white dark:bg-primary-600',
            'success', 'green' => 'bg-green-500 text-white dark:bg-green-600',
            'warning', 'yellow' => 'bg-yellow-500 text-white dark:bg-yellow-600',
            'danger', 'red' => 'bg-red-500 text-white dark:bg-red-600',
            'info', 'blue' => 'bg-blue-500 text-white dark:bg-blue-600',
            default => 'bg-gray-500 text-white dark:bg-gray-600',
        };
    }

    private function outlineClasses(): string
    {
        return match ($this->color) {
            'primary' => 'border border-primary-300 text-primary-700 bg-transparent dark:border-primary-600 dark:text-primary-400',
            'success', 'green' => 'border border-green-300 text-green-700 bg-transparent dark:border-green-600 dark:text-green-400',
            'warning', 'yellow' => 'border border-yellow-300 text-yellow-700 bg-transparent dark:border-yellow-600 dark:text-yellow-400',
            'danger', 'red' => 'border border-red-300 text-red-700 bg-transparent dark:border-red-600 dark:text-red-400',
            'info', 'blue' => 'border border-blue-300 text-blue-700 bg-transparent dark:border-blue-600 dark:text-blue-400',
            default => 'border border-gray-300 text-gray-700 bg-transparent dark:border-gray-600 dark:text-gray-400',
        };
    }

    public function render(): View
    {
        return view('aicl::components.badge');
    }
}
