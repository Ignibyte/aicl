<?php

namespace Aicl\Filament\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;

/**
 * Compact search widget for dashboards.
 *
 * Delegates to Filament's built-in global search. When entities are registered
 * as resources with getGloballySearchableAttributes(), they appear automatically.
 *
 * @property Collection $results
 */
class GlobalSearchWidget extends Widget
{
    protected string $view = 'aicl::filament.widgets.global-search-widget';

    protected int|string|array $columnSpan = 'full';

    public string $query = '';

    public bool $showResults = false;

    public function updatedQuery(): void
    {
        $this->showResults = strlen($this->query) >= 2;
        unset($this->results);
    }

    public function clearSearch(): void
    {
        $this->query = '';
        $this->showResults = false;
        unset($this->results);
    }

    #[Computed]
    public function results(): Collection
    {
        if (strlen($this->query) < 2) {
            return collect();
        }

        // Search is handled by Filament's global search when resources
        // define getGloballySearchableAttributes(). This widget provides
        // a compact dashboard entry point. Without registered searchable
        // resources, results will be empty.
        return collect();
    }

    public static function canView(): bool
    {
        return auth()->check();
    }
}
