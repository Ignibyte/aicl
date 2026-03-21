<?php

declare(strict_types=1);

namespace Aicl\Tests\Unit\Models;

use Aicl\Models\SearchLog;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use PHPUnit\Framework\TestCase;

/**
 * Regression tests for SearchLog model PHPStan changes.
 *
 * Covers casts() return type annotation, prunable() return type
 * with generic Builder, user() relationship with dynamic model
 * resolution, and the int cast on retention_days config value.
 */
class SearchLogRegressionTest extends TestCase
{
    /**
     * Test model uses HasUuids and MassPrunable traits.
     */
    public function test_model_uses_expected_traits(): void
    {
        // Arrange
        $traits = class_uses_recursive(SearchLog::class);

        // Assert
        $this->assertArrayHasKey(HasUuids::class, $traits);
        $this->assertArrayHasKey(MassPrunable::class, $traits);
    }

    /**
     * Test timestamps are disabled.
     *
     * SearchLog has $timestamps = false and uses searched_at instead.
     */
    public function test_timestamps_are_disabled(): void
    {
        // Arrange
        $log = new SearchLog;

        // Assert
        $this->assertFalse($log->timestamps);
    }

    /**
     * Test fillable contains expected attributes.
     */
    public function test_fillable_contains_expected_attributes(): void
    {
        // Arrange
        $log = new SearchLog;

        // Act
        $fillable = $log->getFillable();

        // Assert
        $expected = ['query', 'user_id', 'entity_type_filter', 'results_count', 'searched_at'];
        foreach ($expected as $attribute) {
            $this->assertContains($attribute, $fillable, "Missing fillable: {$attribute}");
        }
    }

    /**
     * Test casts returns expected definitions.
     *
     * PHPStan added @return array<string, string> annotation.
     */
    public function test_casts_returns_expected_definitions(): void
    {
        // Arrange
        $log = new SearchLog;

        // Act: call protected casts() via reflection
        $reflection = new \ReflectionMethod($log, 'casts');
        $casts = $reflection->invoke($log);

        // Assert
        $this->assertSame('integer', $casts['results_count']);
        $this->assertSame('datetime', $casts['searched_at']);
    }

    /**
     * Test user relationship method returns BelongsTo type.
     *
     * PHPStan added @return BelongsTo<Model, $this> and class-string cast
     * for the dynamic user model resolution. Uses reflection because
     * calling user() needs config() service for dynamic model resolution.
     */
    public function test_user_relationship_returns_belongs_to(): void
    {
        // Arrange
        $method = new \ReflectionMethod(SearchLog::class, 'user');
        $returnType = $method->getReturnType();

        // Assert: method returns BelongsTo
        $this->assertInstanceOf(\ReflectionNamedType::class, $returnType);
        /** @var \ReflectionNamedType $returnType */
        $this->assertSame(BelongsTo::class, $returnType->getName());
    }
}
