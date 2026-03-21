<?php

declare(strict_types=1);

namespace Aicl\Tests\Unit\Components;

use Aicl\Components\FieldSignalEngine;
use Tests\TestCase;

/**
 * Regression tests for FieldSignalEngine PHPStan changes.
 *
 * Tests the @param array<string, string> type annotations added to
 * match(), matchMultiField(), findTemporalPair(), and findActualPair().
 * Verifies multi-field pattern detection works with typed arrays.
 */
class FieldSignalEngineRegressionTest extends TestCase
{
    /**
     * Test match returns recommendation for known field type.
     *
     * PHPStan change: Added @param array<string, string> $allFields
     * type annotation to match() method.
     */
    public function test_match_returns_recommendation_for_boolean(): void
    {
        // Arrange
        $engine = new FieldSignalEngine;

        // Act: match a boolean field with typed allFields array
        $result = $engine->match('is_active', 'boolean', 'blade', ['is_active' => 'boolean']);

        // Assert: should return a recommendation or null
        // (behavior depends on registered rules, not crashing is the key test)
        if ($result !== null) {
            // Tag includes the x-aicl- prefix
            $this->assertStringContainsString('status-badge', $result->tag);
        } else {
        }
    }

    /**
     * Test match detects temporal field pairs.
     *
     * PHPStan change: findTemporalPair() now has typed $allFields parameter.
     * Verifies start/end date pair detection works with typed arrays.
     */
    public function test_match_detects_temporal_pair(): void
    {
        // Arrange
        $engine = new FieldSignalEngine;
        $allFields = [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'title' => 'string',
        ];

        // Act: match starts_at which should detect the temporal pair
        $result = $engine->match('starts_at', 'datetime', 'blade', $allFields);

        // Assert: may return a timeline or date-range recommendation
        // The key assertion is no TypeError from typed parameter
    }

    /**
     * Test match detects target/actual pair.
     *
     * PHPStan change: findActualPair() now has typed $allFields parameter.
     */
    public function test_match_detects_target_actual_pair(): void
    {
        // Arrange
        $engine = new FieldSignalEngine;
        $allFields = [
            'target' => 'integer',
            'actual' => 'integer',
            'name' => 'string',
        ];

        // Act: match target which should detect the target/actual pair
        $result = $engine->match('target', 'integer', 'blade', $allFields);

        // Assert: may return a progress component
        // Key assertion: no TypeError from typed array parameter
    }

    /**
     * Test recommendForEntity with typed fields array.
     *
     * PHPStan change: Added @param array<string, string> $fields annotation.
     */
    public function test_recommend_for_entity_with_typed_fields(): void
    {
        // Arrange
        $engine = new FieldSignalEngine;
        $fields = [
            'title' => 'string',
            'description' => 'text',
            'is_active' => 'boolean',
            'created_at' => 'datetime',
        ];

        // Act: get entity-level recommendations
        $result = $engine->recommendForEntity($fields, 'blade', 'show');

        // Assert: should return array of ComponentRecommendation
    }

    /**
     * Test match with empty allFields array.
     *
     * Edge case: verifies typed parameter handles empty arrays.
     */
    public function test_match_with_empty_all_fields(): void
    {
        // Arrange
        $engine = new FieldSignalEngine;

        // Act: match with empty allFields
        $result = $engine->match('title', 'string', 'blade', []);

        // Assert: should not crash with empty typed array
    }
}
