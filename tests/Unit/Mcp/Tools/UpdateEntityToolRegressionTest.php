<?php

declare(strict_types=1);

namespace Aicl\Tests\Unit\Mcp\Tools;

use Aicl\Mcp\Tools\UpdateEntityTool;
use Aicl\Models\AiAgent;
use PHPUnit\Framework\TestCase;

/**
 * Regression tests for UpdateEntityTool MCP tool.
 *
 * Tests the tool's name generation, title, description, and
 * schema generation with fillable fields.
 */
class UpdateEntityToolRegressionTest extends TestCase
{
    // -- name() --

    /**
     * Test name() returns snake_case tool name with update prefix.
     */
    public function test_name_returns_update_prefix(): void
    {
        // Arrange
        $tool = new UpdateEntityTool(AiAgent::class, 'AI Agent');

        // Act
        $name = $tool->name();

        // Assert
        $this->assertSame('update_ai_agent', $name);
    }

    // -- title() --

    /**
     * Test title() returns human-readable update title.
     */
    public function test_title_returns_update_with_entity_label(): void
    {
        // Arrange
        $tool = new UpdateEntityTool(AiAgent::class, 'AI Agent');

        // Act
        $title = $tool->title();

        // Assert
        $this->assertSame('Update AI Agent', $title);
    }

    // -- description() --

    /**
     * Test description() includes fillable fields.
     */
    public function test_description_includes_fillable_fields(): void
    {
        // Arrange
        $tool = new UpdateEntityTool(AiAgent::class, 'AI Agent');

        // Act
        $description = $tool->description();

        // Assert: mentions entity and fillable fields
        $this->assertStringContainsString('AI Agent', $description);
        $this->assertStringContainsString('Updatable fields:', $description);
    }
}
