<?php

namespace Aicl\Filament\Widgets;

use Livewire\Component;

/**
 * Compact toolbar widget showing who else is viewing the same admin page.
 *
 * Uses per-page WebSocket presence channels. Only visible when WebSockets
 * are enabled and other users are on the same page.
 */
class ToolbarPresence extends Component
{
    public function render(): \Illuminate\Contracts\View\View
    {
        return view('aicl::widgets.toolbar-presence');
    }
}
