<?php

declare(strict_types=1);

namespace Aicl\Filament\Widgets;

use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Compact toolbar widget showing who else is viewing the same admin page.
 *
 * Uses per-page WebSocket presence channels. Only visible when WebSockets
 * are enabled and other users are on the same page.
 */
class ToolbarPresence extends Component
{
    public function render(): View
    {
        return view('aicl::widgets.toolbar-presence');
    }
}
