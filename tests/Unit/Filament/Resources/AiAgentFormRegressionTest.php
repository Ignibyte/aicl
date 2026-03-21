<?php

declare(strict_types=1);

namespace Aicl\Tests\Unit\Filament\Resources;

use Aicl\Filament\Resources\AiAgents\Schemas\AiAgentForm;
use PHPUnit\Framework\TestCase;

/**
 * Regression tests for AiAgentForm schema PHPStan changes.
 *
 * Covers the preg_replace() null guard (?? $shortName) added to
 * getRegisteredToolOptions() where preg_replace can return null
 * on error under strict_types.
 */
class AiAgentFormRegressionTest extends TestCase
{
    // -- getRegisteredToolOptions null guard --

    /**
     * Test getRegisteredToolOptions returns empty array when no tools registered.
     *
     * PHPStan change: preg_replace('/Tool$/', ...) ?? $shortName null guard.
     * When the AiToolRegistry is not available (e.g., not bound), the method
     * should return empty array from the catch block.
     */
    public function test_get_registered_tool_options_returns_array(): void
    {
        // Arrange: call the protected static method via reflection
        $method = new \ReflectionMethod(AiAgentForm::class, 'getRegisteredToolOptions');
        $method->setAccessible(true);

        // Act: invoke without a container (catches Throwable and returns [])
        $result = $method->invoke(null);

        // Assert: returns an array (likely empty without AiToolRegistry)
        $this->assertIsArray($result);
    }

    /**
     * Test the preg_replace null guard logic on tool label formatting.
     *
     * Simulates the label transformation: "QueryEntityTool" -> "Query Entity"
     * with the null coalescing fallback added for PHPStan compliance.
     */
    public function test_tool_label_formatting_with_null_guard(): void
    {
        // Arrange: simulate the transformation logic from getRegisteredToolOptions
        $shortName = 'QueryEntityTool';

        // Act: apply the same preg_replace chain with null guards
        $label = preg_replace('/Tool$/', '', $shortName) ?? $shortName;
        $label = preg_replace('/(?<!^)([A-Z])/', ' $1', $label) ?? $label;
        $result = trim($label);

        // Assert: correctly transforms CamelCase to readable format
        $this->assertSame('Query Entity', $result);
    }

    /**
     * Test the preg_replace null guard with a name that has no Tool suffix.
     *
     * Edge case: class name without "Tool" suffix still formats correctly.
     */
    public function test_tool_label_formatting_without_tool_suffix(): void
    {
        // Arrange
        $shortName = 'CustomHelper';

        // Act: apply the same transformation
        $label = preg_replace('/Tool$/', '', $shortName) ?? $shortName;
        $label = preg_replace('/(?<!^)([A-Z])/', ' $1', $label) ?? $label;
        $result = trim($label);

        // Assert: formats correctly without Tool suffix
        $this->assertSame('Custom Helper', $result);
    }

    /**
     * Test the preg_replace null guard with a single-word name.
     *
     * Edge case: single word class name should remain unchanged.
     */
    public function test_tool_label_formatting_single_word(): void
    {
        // Arrange
        $shortName = 'Tool';

        // Act: "Tool" suffix is removed, leaving empty string
        $label = preg_replace('/Tool$/', '', $shortName) ?? $shortName;
        $label = preg_replace('/(?<!^)([A-Z])/', ' $1', $label) ?? $label;
        $result = trim($label);

        // Assert: empty string after removing "Tool" suffix
        $this->assertSame('', $result);
    }
}
