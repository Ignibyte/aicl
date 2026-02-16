<?php

namespace Aicl\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * Horizontal button group for page/section actions.
 *
 * AI Decision Rules:
 * - Use at the top of detail pages for entity actions (Edit, Delete, Export)
 * - Use in card headers for section-specific actions
 * - Slot-based — nest buttons or QuickAction components inside
 */
class ActionBar extends Component
{
    public function __construct(
        public string $align = 'end',
    ) {}

    public function alignClass(): string
    {
        return match ($this->align) {
            'start' => 'justify-start',
            'center' => 'justify-center',
            'between' => 'justify-between',
            default => 'justify-end',
        };
    }

    public function render(): View
    {
        return view('aicl::components.action-bar');
    }
}
