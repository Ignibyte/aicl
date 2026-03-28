<?php

declare(strict_types=1);

namespace Aicl\Search;

use Illuminate\Support\Collection;

/**
 * @codeCoverageIgnore Elasticsearch dependency
 */
class SearchResultCollection
{
    /**
     * @param  Collection<int, SearchResult>  $results
     * @param  array<string, int>  $facets  Entity type → count
     */
    public function __construct(
        public readonly Collection $results,
        public readonly array $facets,
        public readonly int $total,
        public readonly int $page,
        public readonly int $perPage,
    ) {}

    public static function empty(): self
    {
        return new self(
            results: collect(),
            facets: [],
            total: 0,
            page: 1,
            perPage: 20,
        );
    }

    public function totalPages(): int
    {
        if ($this->perPage <= 0) {
            return 0;
        }

        return (int) ceil($this->total / $this->perPage);
    }

    public function hasMorePages(): bool
    {
        return $this->page < $this->totalPages();
    }

    public function isEmpty(): bool
    {
        return $this->results->isEmpty();
    }
}
