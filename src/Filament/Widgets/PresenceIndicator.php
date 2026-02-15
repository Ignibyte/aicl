<?php

namespace Aicl\Filament\Widgets;

use Filament\Widgets\Widget;

class PresenceIndicator extends Widget
{
    protected string $view = 'aicl::widgets.presence-indicator';

    public ?string $channelName = null;

    protected int|string|array $columnSpan = 'full';

    public function mount(?string $channelName = null): void
    {
        $this->channelName = $channelName ?? 'presence-admin-panel';
    }
}
