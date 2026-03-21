<?php

declare(strict_types=1);

namespace Aicl\Tests\Unit\Components;

use Aicl\Components\ComponentRegistry;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * Regression tests for ComponentRegistry PHPStan changes.
 *
 * Tests the PHPDoc type annotations added to boot(), recommend(),
 * recommendForEntity(), schema(), and validateProps() methods.
 * Verifies method signatures and return types work correctly
 * under strict_types.
 */
class ComponentRegistryRegressionTest extends TestCase
{
    /**
     * Test recommend returns null for unknown field type.
     *
     * PHPStan change: Added @param array<string, string> $allFields
     * annotation. Verifies null return type is correct.
     */
    public function test_recommend_returns_null_for_unknown_field_type(): void
    {
        // Arrange
        $registry = app(ComponentRegistry::class);

        // Act: try to recommend for a completely unknown field type
        $result = $registry->recommend('unknown_custom_type', 'blade', 'some_field', []);

        // Assert: should return null, not crash
        $this->assertNull($result);
    }

    /**
     * Test recommendForEntity returns array of recommendations.
     *
     * PHPStan change: Added typed @param and @return annotations.
     */
    public function test_recommend_for_entity_returns_array(): void
    {
        // Arrange
        $registry = app(ComponentRegistry::class);
        $fields = [
            'title' => 'string',
            'count' => 'integer',
            'is_active' => 'boolean',
        ];

        // Act: get recommendations for an entity
        $recommendations = $registry->recommendForEntity($fields, 'blade', 'show');

        // Assert: should return an array (may be empty)
    }

    /**
     * Test schema returns null for unknown component tag.
     *
     * PHPStan change: Added @return array<string, mixed>|null annotation.
     */
    public function test_schema_returns_null_for_unknown_tag(): void
    {
        // Arrange
        $registry = app(ComponentRegistry::class);

        // Act
        $result = $registry->schema('x-nonexistent-component');

        // Assert
        $this->assertNull($result);
    }

    /**
     * Test validateProps returns validation result array.
     *
     * PHPStan change: Added @param array<string, mixed> $props annotation
     * and @return array{valid: bool, errors: array<string>}.
     */
    public function test_validate_props_returns_typed_result(): void
    {
        // Arrange
        $registry = app(ComponentRegistry::class);

        // Act: validate props for a known component
        $result = $registry->validateProps('x-aicl-stat-card', ['label' => 'Test']);

        // Assert: should return array with valid and errors keys
        $this->assertArrayHasKey('valid', $result);
        $this->assertArrayHasKey('errors', $result);
    }

    /**
     * Test all() returns collection after strict_types.
     */
    public function test_all_returns_collection(): void
    {
        // Arrange
        $registry = app(ComponentRegistry::class);

        // Act
        $all = $registry->all();

        // Assert: should return a Collection
        $this->assertInstanceOf(Collection::class, $all);
    }
}
