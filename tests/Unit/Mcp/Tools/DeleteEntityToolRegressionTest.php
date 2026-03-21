<?php

declare(strict_types=1);

namespace Aicl\Tests\Unit\Mcp\Tools;

use Aicl\Mcp\Tools\DeleteEntityTool;
use Aicl\Models\AiAgent;
use PHPUnit\Framework\TestCase;

/**
 * Regression tests for DeleteEntityTool MCP tool.
 *
 * Tests the tool's name generation, title, and description.
 * This tool was added during the MCP server implementation and
 * has strict types enforcement.
 */
class DeleteEntityToolRegressionTest extends TestCase
{
    // -- name() --

    /**
     * Test name() returns snake_case tool name with delete prefix.
     */
    public function test_name_returns_delete_prefix(): void
    {
        // Arrange
        $tool = new DeleteEntityTool(AiAgent::class, 'AI Agent');

        // Act
        $name = $tool->name();

        // Assert
        $this->assertSame('delete_ai_agent', $name);
    }

    // -- title() --

    /**
     * Test title() returns human-readable deletion title.
     */
    public function test_title_returns_delete_with_entity_label(): void
    {
        // Arrange
        $tool = new DeleteEntityTool(AiAgent::class, 'AI Agent');

        // Act
        $title = $tool->title();

        // Assert
        $this->assertSame('Delete AI Agent', $title);
    }

    // -- description() --

    /**
     * Test description() mentions the entity label.
     */
    public function test_description_mentions_entity(): void
    {
        // Arrange
        $tool = new DeleteEntityTool(AiAgent::class, 'AI Agent');

        // Act
        $description = $tool->description();

        // Assert
        $this->assertStringContainsString('AI Agent', $description);
    }
}
