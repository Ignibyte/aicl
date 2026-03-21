<?php

declare(strict_types=1);

namespace Aicl\Tests\Unit\AI\Tools;

use Aicl\AI\Enums\ToolRenderType;
use Aicl\AI\Tools\WhosOnlineTool;
use Tests\TestCase;

/**
 * Regression tests for WhosOnlineTool PHPStan changes.
 *
 * Tests the PHPDoc type annotation additions and variable extraction
 * in formatResultForDisplay(). The PHPStan changes extracted the mixed
 * $result into a typed $resultArray variable before passing to collect().
 */
class WhosOnlineToolRegressionTest extends TestCase
{
    /**
     * Test formatResultForDisplay returns Text type for string input.
     *
     * Non-array input should be treated as plain text.
     */
    public function test_format_result_returns_text_for_string_input(): void
    {
        // Arrange
        $tool = app(WhosOnlineTool::class);

        // Act: pass a string (e.g., "No users online")
        $formatted = $tool->formatResultForDisplay('No users online');

        // Assert: should wrap in Text type
        $this->assertSame(ToolRenderType::Text->value, $formatted['type']);
        $this->assertSame('No users online', $formatted['data']);
    }

    /**
     * Test formatResultForDisplay returns Table type for array input.
     *
     * PHPStan change: $result assigned to typed $resultArray before collect().
     * Verifies the table structure with columns and rows.
     */
    public function test_format_result_returns_table_for_array_input(): void
    {
        // Arrange
        $tool = app(WhosOnlineTool::class);
        $usersData = [
            ['user' => 'Admin', 'role' => 'admin', 'last_seen' => '2 min ago', 'ip' => '192.168.1.1'],
            ['user' => 'Viewer', 'role' => 'viewer', 'last_seen' => '5 min ago', 'ip' => '192.168.1.2'],
        ];

        // Act
        $formatted = $tool->formatResultForDisplay($usersData);

        // Assert: should produce Table type with columns and rows
        $this->assertSame(ToolRenderType::Table->value, $formatted['type']);
        $this->assertSame(['User', 'Role', 'Last Seen', 'IP'], $formatted['data']['columns']);
        $this->assertCount(2, $formatted['data']['rows']);
    }

    /**
     * Test formatResultForDisplay handles missing keys with defaults.
     *
     * PHPStan change: ?? operators ensure missing keys default gracefully.
     */
    public function test_format_result_handles_missing_keys(): void
    {
        // Arrange: data with missing keys
        $tool = app(WhosOnlineTool::class);
        $usersData = [
            [], // completely empty entry
        ];

        // Act
        $formatted = $tool->formatResultForDisplay($usersData);

        // Assert: should use default values ('Unknown', '-') for missing keys
        $row = $formatted['data']['rows'][0];
        $this->assertSame('Unknown', $row['User']);
        $this->assertSame('-', $row['Role']);
        $this->assertSame('-', $row['Last Seen']);
        $this->assertSame('-', $row['IP']);
    }

    /**
     * Test formatResultForDisplay handles empty array input.
     *
     * Edge case: no users online returns empty rows.
     */
    public function test_format_result_handles_empty_array(): void
    {
        // Arrange
        $tool = app(WhosOnlineTool::class);

        // Act
        $formatted = $tool->formatResultForDisplay([]);

        // Assert: should produce Table type with empty rows
        $this->assertSame(ToolRenderType::Table->value, $formatted['type']);
        $this->assertEmpty($formatted['data']['rows']);
    }
}
