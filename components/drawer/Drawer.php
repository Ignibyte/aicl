<?php

namespace Aicl\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * Slide-over drawer panel from left or right edge.
 *
 * AI Decision Rules:
 * - Use for detail panels, filters, and forms that don't need full-page context
 * - Position 'right' (default) for detail/edit panels, 'left' for navigation drawers
 * - In Filament admin, use Action::make()->slideOver() instead
 * - For centered overlays, use <x-aicl-modal> instead
 */
class Drawer extends Component
{
    public function __construct(
        public string $position = 'right',
        public string $width = 'md',
        public bool $overlay = true,
        public bool $closeable = true,
    ) {}

    public function widthClass(): string
    {
        return match ($this->width) {
            'sm' => 'w-80',
            'lg' => 'w-[480px]',
            'xl' => 'w-[640px]',
            default => 'w-96',
        };
    }

    public function positionClasses(): string
    {
        return $this->position === 'left'
            ? 'left-0 top-0'
            : 'right-0 top-0';
    }

    public function enterStart(): string
    {
        return $this->position === 'left' ? '-translate-x-full' : 'translate-x-full';
    }

    public function render(): View
    {
        return view('aicl::components.drawer');
    }
}
