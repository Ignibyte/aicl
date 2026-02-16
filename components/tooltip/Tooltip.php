<?php

namespace Aicl\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * Tooltip with Floating UI positioning and arrow.
 *
 * AI Decision Rules:
 * - Use for supplementary info on hover/focus (icons, abbreviations, truncated text)
 * - Keep content short (1-2 sentences max)
 * - In Filament admin, use ->tooltip() method on columns/actions instead
 */
class Tooltip extends Component
{
    public function __construct(
        public string $content,
        public string $position = 'top',
        public int $delay = 200,
    ) {}

    public function render(): View
    {
        return view('aicl::components.tooltip');
    }
}
