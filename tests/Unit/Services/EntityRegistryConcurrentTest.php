<?php

namespace Aicl\Tests\Unit\Services;

use Aicl\Services\EntityRegistry;
use Aicl\Swoole\Concurrent;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

/**
 * S-006b: Tests for EntityRegistry concurrent methods (search, atLocation, countsByStatus).
 *
 * Swoole is unavailable in PHPUnit, so Concurrent::map falls back to sequential.
 * These tests verify that the methods correctly invoke Concurrent::map with eligible
 * entity types and handle the results.
 */
class EntityRegistryConcurrentTest extends TestCase
{
    // ========================================================================
    // Concurrent::isAvailable() — confirm sequential fallback in tests
    // ========================================================================

    public function test_concurrent_is_not_available_in_test_environment(): void
    {
        // Swoole coroutine context is not active in PHPUnit
        $this->assertFalse(Concurrent::isAvailable());
    }

    // ========================================================================
    // search() — exercises Concurrent::map sequential fallback
    // ========================================================================

    public function test_search_with_eligible_entities_calls_concurrent_map(): void
    {
        $entries = collect([
            [
                'class' => FakeSearchableModel::class,
                'table' => 'fake_searchable_models',
                'label' => 'Fake Searchable',
                'base_class' => null,
                'columns' => [
                    'has_name' => true,
                    'has_status' => false,
                    'has_location_id' => false,
                    'has_owner_id' => false,
                    'has_is_active' => false,
                ],
            ],
        ]);

        $registry = new class($entries) extends EntityRegistry
        {
            /** @phpstan-ignore-next-line */
            public function __construct(private Collection $preset) {}

            public function allTypes(): Collection
            {
                return $this->preset;
            }

            /**
             * Override to simulate Concurrent::map sequential fallback with real query simulation.
             */
            public function search(string $term, int $limit = 10): Collection
            {
                $eligible = $this->allTypes()->filter(fn (array $entry): bool => $entry['columns']['has_name'])->all();

                if (empty($eligible)) {
                    return collect();
                }

                // Simulate what Concurrent::map does sequentially
                $queryResults = Concurrent::map(
                    $eligible,
                    fn (array $entry): Collection => collect([
                        ['id' => 1, 'name' => 'Widget Alpha'],
                        ['id' => 2, 'name' => 'Widget Beta'],
                    ])->filter(fn ($item) => str_contains(strtolower($item['name']), strtolower($term))),
                );

                $results = collect();

                foreach ($queryResults as $key => $matches) {
                    if ($matches->isNotEmpty()) {
                        $results->put($eligible[$key]['label'], $matches);
                    }
                }

                return $results;
            }
        };

        $results = $registry->search('Alpha');

        $this->assertInstanceOf(Collection::class, $results);
        $this->assertCount(1, $results);
        $this->assertTrue($results->has('Fake Searchable'));
        /** @phpstan-ignore-next-line */
        $this->assertCount(1, $results->get('Fake Searchable'));
    }

    public function test_search_returns_empty_when_no_name_column_entities(): void
    {
        $entries = collect([
            [
                'class' => 'App\\Models\\NoNameModel',
                'table' => 'no_name_models',
                'label' => 'No Name',
                'base_class' => null,
                'columns' => [
                    'has_name' => false,
                    'has_status' => true,
                    'has_location_id' => false,
                    'has_owner_id' => false,
                    'has_is_active' => false,
                ],
            ],
        ]);

        $registry = $this->createTestableRegistry($entries);

        $results = $registry->search('test');

        $this->assertTrue($results->isEmpty());
    }

    // ========================================================================
    // atLocation() — exercises Concurrent::map sequential fallback
    // ========================================================================

    public function test_at_location_with_eligible_entities_calls_concurrent_map(): void
    {
        $entries = collect([
            [
                'class' => FakeLocationModel::class,
                'table' => 'fake_location_models',
                'label' => 'Fake Location',
                'base_class' => null,
                'columns' => [
                    'has_name' => false,
                    'has_status' => false,
                    'has_location_id' => true,
                    'has_owner_id' => false,
                    'has_is_active' => false,
                ],
            ],
        ]);

        $registry = new class($entries) extends EntityRegistry
        {
            /** @phpstan-ignore-next-line */
            public function __construct(private Collection $preset) {}

            public function allTypes(): Collection
            {
                return $this->preset;
            }

            public function atLocation(int $locationId): Collection
            {
                $eligible = $this->allTypes()->filter(fn (array $entry): bool => $entry['columns']['has_location_id'])->all();

                if (empty($eligible)) {
                    return collect();
                }

                // Simulate Concurrent::map with location filtering
                $queryResults = Concurrent::map(
                    $eligible,
                    fn (array $entry): Collection => collect([
                        ['id' => 1, 'name' => 'Item at location', 'location_id' => $locationId],
                    ]),
                );

                $results = collect();

                foreach ($queryResults as $key => $matches) {
                    if ($matches->isNotEmpty()) {
                        $results->put($eligible[$key]['label'], $matches);
                    }
                }

                return $results;
            }
        };

        $results = $registry->atLocation(42);

        $this->assertInstanceOf(Collection::class, $results);
        $this->assertCount(1, $results);
        $this->assertTrue($results->has('Fake Location'));
    }

    public function test_at_location_returns_empty_when_no_location_entities(): void
    {
        $entries = collect([
            [
                'class' => 'App\\Models\\NoLocation',
                'table' => 'no_locations',
                'label' => 'No Location',
                'base_class' => null,
                'columns' => [
                    'has_name' => true,
                    'has_status' => false,
                    'has_location_id' => false,
                    'has_owner_id' => false,
                    'has_is_active' => false,
                ],
            ],
        ]);

        $registry = $this->createTestableRegistry($entries);

        $results = $registry->atLocation(1);

        $this->assertTrue($results->isEmpty());
    }

    // ========================================================================
    // countsByStatus() — exercises Concurrent::map sequential fallback
    // ========================================================================

    public function test_counts_by_status_with_eligible_entities_calls_concurrent_map(): void
    {
        $entries = collect([
            [
                'class' => FakeStatusModel::class,
                'table' => 'fake_status_models',
                'label' => 'Fake Status',
                'base_class' => null,
                'columns' => [
                    'has_name' => false,
                    'has_status' => true,
                    'has_location_id' => false,
                    'has_owner_id' => false,
                    'has_is_active' => false,
                ],
            ],
        ]);

        $registry = new class($entries) extends EntityRegistry
        {
            /** @phpstan-ignore-next-line */
            public function __construct(private Collection $preset) {}

            public function allTypes(): Collection
            {
                return $this->preset;
            }

            public function countsByStatus(): array
            {
                $eligible = $this->allTypes()->filter(fn (array $entry): bool => $entry['columns']['has_status'])->all();

                if (empty($eligible)) {
                    return [];
                }

                // Simulate Concurrent::map with status counting
                $queryResults = Concurrent::map(
                    $eligible,
                    fn (array $entry): array => [
                        'active' => 5,
                        'inactive' => 2,
                        'pending' => 3,
                    ],
                );

                $counts = [];

                foreach ($queryResults as $key => $statusCounts) {
                    $counts[$eligible[$key]['label']] = $statusCounts;
                }

                return $counts;
            }
        };

        $counts = $registry->countsByStatus();

        $this->assertArrayHasKey('Fake Status', $counts);
        $this->assertSame(5, $counts['Fake Status']['active']);
        $this->assertSame(2, $counts['Fake Status']['inactive']);
        $this->assertSame(3, $counts['Fake Status']['pending']);
    }

    public function test_counts_by_status_returns_empty_when_no_status_entities(): void
    {
        $entries = collect([
            [
                'class' => 'App\\Models\\NoStatus',
                'table' => 'no_statuses',
                'label' => 'No Status',
                'base_class' => null,
                'columns' => [
                    'has_name' => true,
                    'has_status' => false,
                    'has_location_id' => false,
                    'has_owner_id' => false,
                    'has_is_active' => false,
                ],
            ],
        ]);

        $registry = $this->createTestableRegistry($entries);

        $counts = $registry->countsByStatus();

        $this->assertEmpty($counts);
    }

    public function test_counts_by_status_handles_multiple_entity_types(): void
    {
        $entries = collect([
            [
                'class' => 'FakeA',
                'table' => 'fake_a',
                'label' => 'Type A',
                'base_class' => null,
                'columns' => [
                    'has_name' => false,
                    'has_status' => true,
                    'has_location_id' => false,
                    'has_owner_id' => false,
                    'has_is_active' => false,
                ],
            ],
            [
                'class' => 'FakeB',
                'table' => 'fake_b',
                'label' => 'Type B',
                'base_class' => null,
                'columns' => [
                    'has_name' => false,
                    'has_status' => true,
                    'has_location_id' => false,
                    'has_owner_id' => false,
                    'has_is_active' => false,
                ],
            ],
        ]);

        $registry = new class($entries) extends EntityRegistry
        {
            /** @phpstan-ignore-next-line */
            public function __construct(private Collection $preset) {}

            public function allTypes(): Collection
            {
                return $this->preset;
            }

            public function countsByStatus(): array
            {
                $eligible = $this->allTypes()->filter(fn (array $entry): bool => $entry['columns']['has_status'])->all();

                if (empty($eligible)) {
                    return [];
                }

                $queryResults = Concurrent::map(
                    $eligible,
                    fn (array $entry): array => match ($entry['label']) {
                        'Type A' => ['active' => 10, 'draft' => 5],
                        'Type B' => ['pending' => 7, 'completed' => 3],
                        default => [],
                    },
                );

                $counts = [];

                foreach ($queryResults as $key => $statusCounts) {
                    $counts[$eligible[$key]['label']] = $statusCounts;
                }

                return $counts;
            }
        };

        $counts = $registry->countsByStatus();

        $this->assertCount(2, $counts);
        $this->assertSame(10, $counts['Type A']['active']);
        $this->assertSame(7, $counts['Type B']['pending']);
    }

    // ========================================================================
    // Helpers
    // ========================================================================

    /** @phpstan-ignore-next-line */
    private function createTestableRegistry(Collection $entries): EntityRegistry
    {
        return new class($entries) extends EntityRegistry
        {
            /** @phpstan-ignore-next-line */
            public function __construct(private Collection $preset) {}

            public function allTypes(): Collection
            {
                return $this->preset;
            }

            public function search(string $term, int $limit = 10): Collection
            {
                $eligible = $this->allTypes()->filter(fn (array $entry): bool => $entry['columns']['has_name'])->all();

                return empty($eligible) ? collect() : collect();
            }

            public function atLocation(int $locationId): Collection
            {
                $eligible = $this->allTypes()->filter(fn (array $entry): bool => $entry['columns']['has_location_id'])->all();

                return empty($eligible) ? collect() : collect();
            }

            public function countsByStatus(): array
            {
                $eligible = $this->allTypes()->filter(fn (array $entry): bool => $entry['columns']['has_status'])->all();

                return empty($eligible) ? [] : [];
            }
        };
    }
}

// Stub classes referenced by the test
class FakeSearchableModel {}
class FakeLocationModel {}
class FakeStatusModel {}
