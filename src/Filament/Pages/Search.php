<?php

declare(strict_types=1);

namespace Aicl\Filament\Pages;

use Aicl\Models\SearchLog;
use Aicl\Search\SearchResultCollection;
use Aicl\Search\SearchService;
use BackedEnum;
use Filament\Pages\Page;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use UnitEnum;

/**
 * Full-page search. Queries the unified Elasticsearch index via SearchService.
 *
 * @property SearchResultCollection $searchResults
 *
 * @codeCoverageIgnore Filament Livewire rendering
 */
class Search extends Page
{
    protected static string|BackedEnum|null $navigationIcon = null;

    protected static string|UnitEnum|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 20;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationLabel = 'Search';

    protected static ?string $title = 'Search';

    protected static ?string $slug = 'search';

    protected string $view = 'aicl::filament.pages.search';

    #[Url(as: 'q')]
    public string $query = '';

    #[Url(as: 'type')]
    public string $entityType = '';

    #[Url(as: 'page')]
    public int $page = 1;

    public function updatedQuery(): void
    {
        $this->page = 1;
        unset($this->searchResults);
    }

    public function updatedEntityType(): void
    {
        $this->page = 1;
        unset($this->searchResults);
    }

    public function goToPage(int $page): void
    {
        $this->page = $page;
        unset($this->searchResults);
    }

    #[Computed]
    public function searchResults(): SearchResultCollection
    {
        if (! config('aicl.search.enabled', false)) {
            return SearchResultCollection::empty();
        }

        $minLength = (int) config('aicl.search.min_query_length', 2);

        if (strlen(trim($this->query)) < $minLength) {
            return SearchResultCollection::empty();
        }

        $user = auth()->user();

        if ($user === null) {
            return SearchResultCollection::empty();
        }

        $searchService = app(SearchService::class);

        $results = $searchService->search(
            query: $this->query,
            user: $user,
            entityTypeFilter: $this->entityType ?: null,
            page: $this->page,
            perPage: 20,
        );

        // Apply policy safety-net
        $filtered = $searchService->applyPolicyFilter($results->results, $user);

        // Log the search query for analytics
        if (config('aicl.search.analytics.enabled', true)) {
            SearchLog::query()->create([
                'query' => $this->query,
                'user_id' => $user->getAuthIdentifier(),
                'entity_type_filter' => $this->entityType,
                'results_count' => $results->total,
                'searched_at' => now(),
            ]);
        }

        return new SearchResultCollection(
            results: $filtered,
            facets: $results->facets,
            total: $results->total,
            page: $results->page,
            perPage: $results->perPage,
        );
    }

    /**
     * @return array<string, string>
     */
    public function getEntityTypes(): array
    {
        if (! config('aicl.search.enabled', false)) {
            return ['' => 'All Types'];
        }

        try {
            return app(SearchService::class)->getEntityTypes();
        } catch (\Throwable) {
            return ['' => 'All Types'];
        }
    }

    public static function canAccess(): bool
    {
        return auth()->check();
    }
}
