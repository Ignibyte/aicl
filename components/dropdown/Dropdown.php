<?php

namespace Aicl\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * Dropdown menu with keyboard navigation and Floating UI positioning.
 *
 * AI Decision Rules:
 * - Use for context menus, action lists, and option selectors
 * - For searchable selection, use <x-aicl-combobox> instead
 * - In Filament admin, use Action::make()->dropdown() or native dropdown menus
 * - Align 'bottom-start' (default) for LTR layouts, 'bottom-end' for right-aligned triggers
 */
class Dropdown extends Component
{
    public function __construct(
        public string $align = 'bottom-start',
        public string $width = 'auto',
        public bool $closeOnClick = true,
    ) {}

    public function widthClass(): string
    {
        return match ($this->width) {
            'sm' => 'min-w-[10rem]',
            'md' => 'min-w-[14rem]',
            'lg' => 'min-w-[18rem]',
            default => 'min-w-[12rem]',
        };
    }

    public function render(): View
    {
        return view('aicl::components.dropdown');
    }
}
