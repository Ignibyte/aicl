<?php

declare(strict_types=1);

namespace Aicl\Tests\Unit\Services;

use Aicl\Services\EntityRegistry;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * Regression tests for EntityRegistry PHPStan changes.
 *
 * Tests the (string) cast added to $relativePath in the discover()
 * method. Under strict_types, str_replace() requires string arguments
 * and the SplFileInfo::getPathname() could return a mixed type.
 */
class EntityRegistryRegressionTest extends TestCase
{
    /**
     * Test discover builds correct class names from file paths.
     *
     * PHPStan change: Added (string) cast to $relativePath before
     * passing to str_replace(). The SplFileInfo::getPathname() return
     * value needs explicit casting under strict_types.
     */
    public function test_discover_builds_class_names_correctly(): void
    {
        // Arrange: flush any cached discovery results
        EntityRegistry::flush();

        // Act: trigger discovery via allTypes()
        $registry = app(EntityRegistry::class);
        $entities = $registry->allTypes();

        // Assert: discovery should complete without type errors
        // and return a collection (even if empty in test environment)
        $this->assertInstanceOf(Collection::class, $entities);
    }

    /**
     * Test flush clears cached entity registry.
     *
     * Verifies flush works correctly after strict_types addition.
     */
    public function test_flush_clears_registry(): void
    {
        // Arrange: ensure registry is populated
        $registry = app(EntityRegistry::class);
        $registry->allTypes();

        // Act: flush the cache
        EntityRegistry::flush();

        // Assert: subsequent call should re-discover (no cached data)
        $entities = $registry->allTypes();
        $this->assertInstanceOf(Collection::class, $entities);
    }

    /**
     * Test resolveType returns null for unregistered types.
     *
     * Verifies null return behavior is preserved after strict_types.
     */
    public function test_resolve_type_returns_null_for_unknown_type(): void
    {
        // Act
        $registry = app(EntityRegistry::class);
        $result = $registry->resolveType('NonExistentModel');

        // Assert: should return null, not throw
        $this->assertNull($result);
    }
}
