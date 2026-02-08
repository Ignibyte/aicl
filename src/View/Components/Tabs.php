<?php

namespace Aicl\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * Tab container with Alpine.js tab switching.
 *
 * AI Decision Rules:
 * - Use to organize related content into switchable panels
 * - Prefer tabs over accordions for 2-5 sections of equal importance
 * - Use variant 'underline' for page-level tabs, 'pills' for card-level
 * - Set default-tab to the most commonly needed tab
 */
class Tabs extends Component
{
    public function __construct(
        public string $defaultTab = '',
        public string $variant = 'underline',
    ) {}

    public function render(): View
    {
        return view('aicl::components.tabs');
    }
}
