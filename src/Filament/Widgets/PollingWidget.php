<?php

namespace Aicl\Filament\Widgets;

use Filament\Widgets\Widget;

/**
 * Abstract base class for widgets that auto-refresh via Livewire polling.
 *
 * Provides configurable polling interval and visibility-aware pausing to
 * reduce unnecessary server requests when the browser tab is not active.
 * Subclasses override poll() to refresh their data on each tick.
 */
abstract class PollingWidget extends Widget
{
    protected string $view = 'aicl::widgets.polling-widget';

    /**
     * Polling interval in seconds. Override to change.
     */
    public function pollingInterval(): int
    {
        return 60;
    }

    /**
     * Whether to pause polling when the tab is not visible.
     */
    public function pauseWhenHidden(): bool
    {
        return true;
    }

    /**
     * Livewire method called on each poll tick.
     * Subclasses override to refresh data.
     */
    public function poll(): void
    {
        $this->dispatch('poll-tick');
    }
}
