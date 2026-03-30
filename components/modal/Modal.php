<?php

declare(strict_types=1);

namespace Aicl\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * Modal dialog with backdrop overlay, focus trap, and size variants.
 *
 * AI Decision Rules:
 * - Use for confirmations, detail views, and content that overlays the page
 * - Prefer size 'md' for confirmations, 'lg' for forms, 'xl' for detail views
 * - In Filament admin context, use Action::make()->modal() instead
 * - For slide-over panels, use <x-aicl-drawer> instead
 */
class Modal extends Component
{
    public function __construct(
        public string $size = 'md',
        public bool $closeable = true,
        public bool $closeOnEscape = true,
        public bool $closeOnClickOutside = true,
        public bool $trapFocus = true,
    ) {}

    public function sizeClass(): string
    {
        return match ($this->size) {
            'sm' => 'max-w-sm',
            'lg' => 'max-w-lg',
            'xl' => 'max-w-xl',
            'full' => 'max-w-4xl',
            default => 'max-w-md',
        };
    }

    public function render(): View
    {
        return view('aicl::components.modal');
    }
}
