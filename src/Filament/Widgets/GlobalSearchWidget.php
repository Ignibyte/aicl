<?php

namespace Aicl\Filament\Widgets;

use Aicl\Search\SearchResultCollection;
use Aicl\Search\SearchService;
use Filament\Widgets\Widget;
use Livewire\Attributes\Computed;

/**
 * Compact search widget for dashboards.
 *
 * Queries the unified Elasticsearch index via SearchService.
 *
 * @property SearchResultCollection $results
 */
class GlobalSearchWidget extends Widget
{
    protected string $view = 'aicl::filament.widgets.global-search-widget';

    protected int|string|array $columnSpan = 'full';

    public string $query = '';

    public bool $showResults = false;

    public function updatedQuery(): void
    {
        $this->showResults = strlen($this->query) >= (int) config('aicl.search.min_query_length', 2);
        unset($this->results);
    }

    public function clearSearch(): void
    {
        $this->query = '';
        $this->showResults = false;
        unset($this->results);
    }

    #[Computed]
    public function results(): SearchResultCollection
    {
        $minLength = (int) config('aicl.search.min_query_length', 2);

        if (strlen($this->query) < $minLength || ! config('aicl.search.enabled', false)) {
            return SearchResultCollection::empty();
        }

        $user = auth()->user();

        if ($user === null) {
            return SearchResultCollection::empty();
        }

        return app(SearchService::class)->search(
            query: $this->query,
            user: $user,
            perPage: 5,
        );
    }

    public static function canView(): bool
    {
        return auth()->check() && config('aicl.search.enabled', false);
    }
}
