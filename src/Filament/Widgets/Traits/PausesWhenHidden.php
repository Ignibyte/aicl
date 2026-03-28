<?php

declare(strict_types=1);

namespace Aicl\Filament\Widgets\Traits;

/**
 * Adds Page Visibility API-aware polling to any Filament widget.
 *
 * When the browser tab is hidden, polling stops entirely.
 * When the tab becomes visible again, a catch-up poll fires immediately
 * and regular polling resumes.
 *
 * Usage: Add `use PausesWhenHidden;` to any Filament widget that has
 * a polling interval. The trait disables Filament's built-in wire:poll
 * and replaces it with a JS-based visibility-aware implementation.
 *
 * Override `visibilityPollingInterval()` to change the interval (default: 60s).
 */
trait PausesWhenHidden
{
    /**
     * Disable Filament's built-in wire:poll.
     * We handle polling ourselves via JS.
     */
    public function getPollingInterval(): ?string
    {
        return null;
    }

    /**
     * Polling interval in seconds.
     * Override in widget classes to customize.
     */
    public function visibilityPollingInterval(): int
    {
        return 60;
    }

    /**
     * Boot hook — injects visibility-aware polling JS via Livewire.
     * Called automatically by Livewire's trait boot convention.
     */
    public function bootPausesWhenHidden(): void
    {
        $interval = $this->visibilityPollingInterval();

        $this->js(<<<JS
            if (!window.__pauseWhenHidden_{$this->getId()}) {
                window.__pauseWhenHidden_{$this->getId()} = true;
                let timer = null;
                const interval = {$interval} * 1000;

                function startPolling() {
                    stopPolling();
                    timer = setInterval(() => \$wire.\$refresh(), interval);
                }

                function stopPolling() {
                    if (timer) { clearInterval(timer); timer = null; }
                }

                document.addEventListener('visibilitychange', () => {
                    if (document.hidden) {
                        stopPolling();
                    } else {
                        \$wire.\$refresh();
                        startPolling();
                    }
                });

                startPolling();
            }
        JS);
    }
}
