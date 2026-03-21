<?php

declare(strict_types=1);

namespace Aicl\Tests\Unit\Components;

use Aicl\Components\ComponentRecommendation;
use PHPUnit\Framework\TestCase;

/**
 * Regression tests for ComponentRecommendation PHPStan changes.
 *
 * Tests the @param array<string, mixed> $suggestedProps constructor
 * annotation and @return array<string, mixed> on toArray().
 * Verifies typed arrays work correctly under strict_types.
 */
class ComponentRecommendationRegressionTest extends TestCase
{
    /**
     * Test constructor with typed suggestedProps parameter.
     *
     * PHPStan change: Added @param array<string, mixed> annotation
     * to constructor's $suggestedProps parameter.
     */
    public function test_constructor_accepts_typed_suggested_props(): void
    {
        // Arrange: create with mixed-type props array
        $recommendation = new ComponentRecommendation(
            tag: 'x-aicl-stat-card',
            reason: 'Integer field matches stat card pattern',
            suggestedProps: [
                'label' => 'Total Count',
                'icon' => 'heroicon-o-chart-bar',
            ],
            confidence: 0.85,
            alternative: null,
        );

        // Assert: should store all props correctly
        $this->assertSame('x-aicl-stat-card', $recommendation->tag);
        $this->assertSame('Integer field matches stat card pattern', $recommendation->reason);
        $this->assertSame('Total Count', $recommendation->suggestedProps['label']);
        $this->assertSame(0.85, $recommendation->confidence);
    }

    /**
     * Test toArray returns typed array.
     *
     * PHPStan change: Added @return array<string, mixed> annotation.
     */
    public function test_to_array_returns_typed_array(): void
    {
        // Arrange
        $recommendation = new ComponentRecommendation(
            tag: 'x-aicl-status-badge',
            reason: 'Boolean field suggests status display',
            suggestedProps: ['trueLabel' => 'Active', 'falseLabel' => 'Inactive'],
            confidence: 0.9,
            alternative: 'x-aicl-toggle',
        );

        // Act
        $array = $recommendation->toArray();

        // Assert: should return well-typed array with all keys
        $this->assertSame('x-aicl-status-badge', $array['tag']);
        $this->assertSame('Boolean field suggests status display', $array['reason']);
        $this->assertIsArray($array['suggestedProps']);
        $this->assertSame(0.9, $array['confidence']);
        $this->assertSame('x-aicl-toggle', $array['alternative']);
    }

    /**
     * Test constructor with empty suggestedProps.
     *
     * Edge case: no suggested props.
     */
    public function test_constructor_with_empty_suggested_props(): void
    {
        // Arrange
        $recommendation = new ComponentRecommendation(
            tag: 'x-aicl-divider',
            reason: 'Layout separator',
            suggestedProps: [],
            confidence: 1.0,
            alternative: null,
        );

        // Assert
        $this->assertEmpty($recommendation->suggestedProps);
        $this->assertNull($recommendation->alternative);
        $array = $recommendation->toArray();
        $this->assertEmpty($array['suggestedProps']);
    }
}
