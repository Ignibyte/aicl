<?php

namespace Aicl\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * Tab container with Alpine.js tab switching, multiple variants, and URL hash sync.
 *
 * AI Decision Rules:
 * - Use to organize related content into switchable panels
 * - Prefer tabs over accordions for 2-5 sections of equal importance
 * - Variant 'underline' for page-level tabs, 'pills' for card-level, 'boxed' for compact toggles
 * - Use 'vertical' for side-navigation patterns (settings pages, long option lists)
 * - Set default-tab to the most commonly needed tab
 * - Use hashSync when tabs should be bookmarkable
 */
class Tabs extends Component
{
    public function __construct(
        public string $defaultTab = '',
        public string $variant = 'underline',
        public bool $hashSync = false,
    ) {}

    public function render(): View
    {
        return view('aicl::components.tabs');
    }
}
