<?php

declare(strict_types=1);

namespace Aicl\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * Toast notification container with auto-dismiss and stacking.
 *
 * AI Decision Rules:
 * - Use for transient success/error/info/warning notifications
 * - Place once per page layout (not per component)
 * - Toasts are triggered via Alpine.store('toasts').add({ type, title, message })
 * - In Filament admin, use Notification::make() system instead
 */
class Toast extends Component
{
    public function __construct(
        public string $position = 'top-right',
        public int $maxVisible = 5,
    ) {}

    public function positionClasses(): string
    {
        return match ($this->position) {
            'top-left' => 'top-4 left-4',
            'bottom-right' => 'bottom-4 right-4',
            'bottom-left' => 'bottom-4 left-4',
            'top-center' => 'top-4 left-1/2 -translate-x-1/2',
            default => 'top-4 right-4',
        };
    }

    public function render(): View
    {
        return view('aicl::components.toast');
    }
}
