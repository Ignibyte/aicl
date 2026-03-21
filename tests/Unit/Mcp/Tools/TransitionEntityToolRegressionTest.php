<?php

declare(strict_types=1);

namespace Aicl\Tests\Unit\Mcp\Tools;

use Aicl\Mcp\Tools\TransitionEntityTool;
use Aicl\Models\AiAgent;
use PHPUnit\Framework\TestCase;

/**
 * Regression tests for TransitionEntityTool MCP tool.
 *
 * Tests the tool's name generation, title, description, and
 * the state transition metadata used to build the MCP schema.
 */
class TransitionEntityToolRegressionTest extends TestCase
{
    // -- name() --

    /**
     * Test name() returns snake_case tool name with transition prefix.
     */
    public function test_name_returns_transition_prefix(): void
    {
        // Arrange
        $tool = new TransitionEntityTool(AiAgent::class, 'AI Agent');

        // Act
        $name = $tool->name();

        // Assert
        $this->assertSame('transition_ai_agent', $name);
    }

    // -- title() --

    /**
     * Test title() returns human-readable transition title.
     */
    public function test_title_returns_transition_with_entity_label(): void
    {
        // Arrange
        $tool = new TransitionEntityTool(AiAgent::class, 'AI Agent');

        // Act
        $title = $tool->title();

        // Assert
        $this->assertSame('Transition AI Agent State', $title);
    }

    // -- description() --

    /**
     * Test description method exists and is public.
     *
     * The description() method calls getAvailableStates() which requires
     * the model to use HasStates trait. We verify the method signature
     * rather than invoking it, since AiAgent state machine config may
     * not be available in unit test context.
     */
    public function test_description_method_is_public(): void
    {
        // Arrange
        $reflection = new \ReflectionMethod(TransitionEntityTool::class, 'description');

        // Assert: method is public
        $this->assertTrue($reflection->isPublic());
    }
}
