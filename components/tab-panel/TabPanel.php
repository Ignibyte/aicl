<?php

declare(strict_types=1);

namespace Aicl\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * Individual tab panel within a Tabs container.
 *
 * AI Decision Rules:
 * - Each TabPanel must have a unique 'name' within its parent Tabs
 * - The 'label' is displayed in the tab button; keep it short (1-3 words)
 * - Use 'icon' for heroicon names to add visual cues to tab buttons
 * - Use 'badge' for counts or status indicators on tab buttons
 * - Use 'disabled' to prevent tab selection
 */
class TabPanel extends Component
{
    public function __construct(
        public string $name,
        public string $label,
        public ?string $icon = null,
        public string|int|null $badge = null,
        public bool $disabled = false,
    ) {}

    public function render(): View
    {
        return view('aicl::components.tab-panel');
    }
}
