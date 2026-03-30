<?php

declare(strict_types=1);

namespace Aicl\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * Dismissible alert banner.
 *
 * AI Decision Rules:
 * - Use for system-wide messages (maintenance, feature announcements)
 * - Use for contextual warnings within a section
 * - Type determines color: info (blue), success (green), warning (yellow), danger (red)
 */
class AlertBanner extends Component
{
    public function __construct(
        public string $type = 'info',
        public ?string $icon = null,
        public bool $dismissible = true,
    ) {}

    public function typeClasses(): string
    {
        return match ($this->type) {
            'success' => 'border-green-200 bg-green-50 text-green-800 dark:border-green-800 dark:bg-green-900/20 dark:text-green-400',
            'warning' => 'border-yellow-200 bg-yellow-50 text-yellow-800 dark:border-yellow-800 dark:bg-yellow-900/20 dark:text-yellow-400',
            'danger', 'error' => 'border-red-200 bg-red-50 text-red-800 dark:border-red-800 dark:bg-red-900/20 dark:text-red-400',
            default => 'border-blue-200 bg-blue-50 text-blue-800 dark:border-blue-800 dark:bg-blue-900/20 dark:text-blue-400',
        };
    }

    public function defaultIcon(): string
    {
        if ($this->icon) {
            return $this->icon;
        }

        return match ($this->type) {
            'success' => 'heroicon-o-check-circle',
            'warning' => 'heroicon-o-exclamation-triangle',
            'danger', 'error' => 'heroicon-o-x-circle',
            default => 'heroicon-o-information-circle',
        };
    }

    public function render(): View
    {
        return view('aicl::components.alert-banner');
    }
}
