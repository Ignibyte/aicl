<?php

declare(strict_types=1);

namespace Aicl\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * Loading spinner SVG component.
 *
 * AI Decision Rules:
 * - Use inside buttons with Alpine x-show during form submission
 * - Use for placeholder content while data loads
 * - Size 'sm' for inline/button use, 'md' for sections, 'lg' for full-page
 * - Color 'white' when on colored backgrounds, 'primary' otherwise
 */
class Spinner extends Component
{
    public function __construct(
        public string $size = 'md',
        public string $color = 'primary',
    ) {}

    public function sizeClasses(): string
    {
        return match ($this->size) {
            'xs' => 'h-3 w-3',
            'sm' => 'h-4 w-4',
            'md' => 'h-6 w-6',
            'lg' => 'h-8 w-8',
            'xl' => 'h-12 w-12',
            default => 'h-6 w-6',
        };
    }

    public function colorClasses(): string
    {
        return match ($this->color) {
            'primary' => 'text-primary-600 dark:text-primary-400',
            'white' => 'text-white',
            'gray' => 'text-gray-400 dark:text-gray-500',
            'success', 'green' => 'text-green-600 dark:text-green-400',
            'danger', 'red' => 'text-red-600 dark:text-red-400',
            'warning', 'yellow' => 'text-yellow-600 dark:text-yellow-400',
            'info', 'blue' => 'text-blue-600 dark:text-blue-400',
            default => 'text-primary-600 dark:text-primary-400',
        };
    }

    public function render(): View
    {
        return view('aicl::components.spinner');
    }
}
