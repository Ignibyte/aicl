<?php

namespace Aicl\Tests\Unit\Search;

use Aicl\Search\SearchResult;
use Aicl\Search\SearchResultCollection;
use Tests\TestCase;

class SearchResultCollectionTest extends TestCase
{
    public function test_empty_returns_empty_collection(): void
    {
        $collection = SearchResultCollection::empty();

        $this->assertTrue($collection->isEmpty());
        $this->assertSame(0, $collection->total);
        $this->assertSame(1, $collection->page);
        $this->assertSame(20, $collection->perPage);
        $this->assertEmpty($collection->facets);
    }

    public function test_total_pages_calculates_correctly(): void
    {
        $collection = new SearchResultCollection(
            results: collect(),
            facets: [],
            total: 45,
            page: 1,
            perPage: 20,
        );

        $this->assertSame(3, $collection->totalPages());
    }

    public function test_has_more_pages(): void
    {
        $collection = new SearchResultCollection(
            results: collect(),
            facets: [],
            total: 45,
            page: 1,
            perPage: 20,
        );

        $this->assertTrue($collection->hasMorePages());

        $collection2 = new SearchResultCollection(
            results: collect(),
            facets: [],
            total: 45,
            page: 3,
            perPage: 20,
        );

        $this->assertFalse($collection2->hasMorePages());
    }

    public function test_is_empty_with_results(): void
    {
        $result = new SearchResult(
            entityType: 'App\\Models\\User',
            entityId: '1',
            title: 'Test',
            subtitle: null,
            url: '/admin/users/1',
            icon: 'heroicon-o-user',
            score: 1.0,
        );

        $collection = new SearchResultCollection(
            results: collect([$result]),
            facets: ['App\\Models\\User' => 1],
            total: 1,
            page: 1,
            perPage: 20,
        );

        $this->assertFalse($collection->isEmpty());
    }
}
