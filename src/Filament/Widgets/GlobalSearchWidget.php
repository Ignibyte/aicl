<?php

declare(strict_types=1);

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
 *
 * @codeCoverageIgnore Filament Livewire rendering
 */
class GlobalSearchWidget extends Widget
{
    /**
     * Over-fetch multiplier for the SearchService::search call.
     *
     * Requests `perPage * OVER_FETCH_MULTIPLIER` hits from Elasticsearch so
     * that after `applyPolicyFilter` drops policy-protected results, the
     * widget can still fill `perPage` items in the dropdown. At perPage=5
     * and multiplier=3, this handles a policy-drop rate up to 66% without
     * under-populating the UI. Higher drop rates (pathological) degrade
     * gracefully — the widget shows fewer items but `total` still reflects
     * the ES-raw count so the user can visit the full Search page.
     */
    private const int OVER_FETCH_MULTIPLIER = 3;

    /**
     * Display page size for the dropdown.
     */
    private const int PER_PAGE = 5;

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

    /**
     * Resolve the widget's visible results.
     *
     * Over-fetches `perPage * OVER_FETCH_MULTIPLIER` from Elasticsearch so
     * `applyPolicyFilter` can drop policy-protected results and still fill
     * the dropdown with up to `perPage` visible items. The returned `total`
     * is the ES-raw count (`$collection->total`) — matches the Search page
     * semantics at `packages/aicl/src/Filament/Pages/Search.php:115`.
     *
     * The previous implementation reported `total: $filteredResults->count()`
     * (the current-page filtered count) AND requested only `perPage: 5` from
     * ES. That combination meant (a) pagination-aware callers saw a `total`
     * that shrank with every policy drop, and (b) the dropdown could show as
     * few as 0 items even when plenty of policy-passing matches existed on
     * the next ES page. See pipeline doc for the full analysis — architecture
     * decision `search.widget-pagination-over-fetch`.
     */
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

        $searchService = app(SearchService::class);

        // Over-fetch from ES. See OVER_FETCH_MULTIPLIER docblock for rationale.
        $collection = $searchService->search(
            query: $this->query,
            user: $user,
            perPage: self::PER_PAGE * self::OVER_FETCH_MULTIPLIER,
        );

        // Defense-in-depth safety-net: post-filter results against Laravel policies.
        // Matches the Search page's applyPolicyFilter call. Without this, entities
        // with `visibility=policy` would rely solely on the ES query-level filter,
        // which `PermissionFilterBuilder` intentionally leaves open for the PHP
        // policy layer to enforce.
        $filteredResults = $searchService->applyPolicyFilter($collection->results, $user);

        // Slice to the display page size — we over-fetched to survive policy drops.
        $displayResults = $filteredResults->take(self::PER_PAGE);

        return new SearchResultCollection(
            results: $displayResults,
            facets: $collection->facets,
            total: $collection->total,
            page: $collection->page,
            perPage: self::PER_PAGE,
        );
    }

    public static function canView(): bool
    {
        return auth()->check() && config('aicl.search.enabled', false);
    }
}
