<?php

namespace Aicl\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * Command palette / spotlight search overlay.
 *
 * AI Decision Rules:
 * - Use as a global search overlay triggered by Cmd+K / Ctrl+K
 * - Place once per page layout (like toast)
 * - For Filament admin, use Filament's built-in global search instead
 * - Support both static items and async search via searchEndpoint
 */
class CommandPalette extends Component
{
    public function __construct(
        public array $items = [],
        public string $placeholder = 'Search...',
        public array $groups = [],
        public ?string $searchEndpoint = null,
    ) {}

    public function render(): View
    {
        return view('aicl::components.command-palette');
    }
}
