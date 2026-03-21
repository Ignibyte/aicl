<?php

declare(strict_types=1);

namespace Aicl\Tests\Unit\AI\Tools;

use Aicl\AI\Enums\ToolRenderType;
use Aicl\AI\Tools\HealthStatusTool;
use Tests\TestCase;

/**
 * Regression tests for HealthStatusTool PHPStan changes.
 *
 * Tests the PHPDoc type annotation additions and variable extraction
 * in formatResultForDisplay(). The PHPStan changes extracted the mixed
 * $result into a typed $resultArray variable before passing to collect().
 */
class HealthStatusToolRegressionTest extends TestCase
{
    /**
     * Test formatResultForDisplay returns Text type for string input.
     *
     * Non-array input should be treated as plain text, not status data.
     */
    public function test_format_result_returns_text_for_string_input(): void
    {
        // Arrange
        $tool = app(HealthStatusTool::class);

        // Act: pass a string result (error message scenario)
        $formatted = $tool->formatResultForDisplay('All systems operational');

        // Assert: should wrap in Text type
        $this->assertSame(ToolRenderType::Text->value, $formatted['type']);
        $this->assertSame('All systems operational', $formatted['data']);
    }

    /**
     * Test formatResultForDisplay returns Status type for array input.
     *
     * PHPStan change: $result is now assigned to typed $resultArray
     * before passing to collect(). Verifies array input produces
     * properly structured status output.
     */
    public function test_format_result_returns_status_type_for_array_input(): void
    {
        // Arrange
        $tool = app(HealthStatusTool::class);
        $healthData = [
            ['service' => 'PostgreSQL', 'status' => 'healthy', 'details' => 'Connected'],
            ['service' => 'Redis', 'status' => 'healthy', 'details' => ['latency' => '1ms']],
        ];

        // Act
        $formatted = $tool->formatResultForDisplay($healthData);

        // Assert: should produce Status render type with items array
        $this->assertSame(ToolRenderType::Status->value, $formatted['type']);
        $this->assertIsArray($formatted['data']['items']);
        $this->assertCount(2, $formatted['data']['items']);

        // Verify item structure
        $this->assertSame('PostgreSQL', $formatted['data']['items'][0]['label']);
        $this->assertSame('healthy', $formatted['data']['items'][0]['status']);
        $this->assertSame('Connected', $formatted['data']['items'][0]['detail']);
    }

    /**
     * Test formatResultForDisplay handles missing service key.
     *
     * PHPStan change: Added ?? 'Unknown' default for missing 'service' key.
     */
    public function test_format_result_defaults_service_to_unknown(): void
    {
        // Arrange: result without 'service' key
        $tool = app(HealthStatusTool::class);
        $healthData = [
            ['status' => 'healthy', 'details' => null],
        ];

        // Act
        $formatted = $tool->formatResultForDisplay($healthData);

        // Assert: missing service should default to 'Unknown'
        $this->assertSame('Unknown', $formatted['data']['items'][0]['label']);
    }

    /**
     * Test formatResultForDisplay handles array details via json_encode.
     *
     * When 'details' is an array, it should be json_encoded for display.
     */
    public function test_format_result_json_encodes_array_details(): void
    {
        // Arrange
        $tool = app(HealthStatusTool::class);
        $healthData = [
            ['service' => 'ES', 'status' => 'healthy', 'details' => ['version' => '8.17', 'nodes' => 1]],
        ];

        // Act
        $formatted = $tool->formatResultForDisplay($healthData);

        // Assert: array details should be JSON encoded
        $detail = $formatted['data']['items'][0]['detail'];
        $this->assertIsString($detail);
        $this->assertStringContainsString('8.17', $detail);
    }

    /**
     * Test formatResultForDisplay returns empty items for empty array.
     *
     * Edge case: empty health data array.
     */
    public function test_format_result_handles_empty_array(): void
    {
        // Arrange
        $tool = app(HealthStatusTool::class);

        // Act
        $formatted = $tool->formatResultForDisplay([]);

        // Assert: should produce Status type with empty items
        $this->assertSame(ToolRenderType::Status->value, $formatted['type']);
        $this->assertEmpty($formatted['data']['items']);
    }
}
