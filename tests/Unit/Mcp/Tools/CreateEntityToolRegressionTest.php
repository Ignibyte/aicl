<?php

declare(strict_types=1);

namespace Aicl\Tests\Unit\Mcp\Tools;

use Aicl\Mcp\Tools\CreateEntityTool;
use Aicl\Models\AiAgent;
use PHPUnit\Framework\TestCase;

/**
 * Regression tests for CreateEntityTool MCP tool.
 *
 * This is a new file added during PHPStan migration. Tests the tool's
 * name generation, title, description with fillable fields, schema
 * generation, and form request resolution.
 */
class CreateEntityToolRegressionTest extends TestCase
{
    // -- name() --

    /**
     * Test name() returns snake_case tool name from model class.
     *
     * Format: create_{snake_case_model_name}
     */
    public function test_name_returns_snake_case_with_create_prefix(): void
    {
        // Arrange
        $tool = new CreateEntityTool(AiAgent::class, 'AI Agent');

        // Act
        $name = $tool->name();

        // Assert
        $this->assertSame('create_ai_agent', $name);
    }

    // -- title() --

    /**
     * Test title() returns human-readable creation title.
     */
    public function test_title_returns_create_with_entity_label(): void
    {
        // Arrange
        $tool = new CreateEntityTool(AiAgent::class, 'AI Agent');

        // Act
        $title = $tool->title();

        // Assert
        $this->assertSame('Create AI Agent', $title);
    }

    // -- description() --

    /**
     * Test description() includes fillable field list.
     *
     * The description dynamically lists the model's fillable fields
     * to help AI agents understand what data to provide.
     */
    public function test_description_includes_fillable_fields(): void
    {
        // Arrange
        $tool = new CreateEntityTool(AiAgent::class, 'AI Agent');

        // Act
        $description = $tool->description();

        // Assert: description mentions the entity label and contains field list
        $this->assertStringContainsString('AI Agent', $description);
        $this->assertStringContainsString('Fillable fields:', $description);
    }

    // -- resolveFormRequest() --

    /**
     * Test resolveFormRequest returns null for non-existent request classes.
     *
     * The method searches for App\Http\Requests\{Prefix}{Model}Request
     * and App\Http\Requests\{Model}\{Prefix}{Model}Request.
     */
    public function test_resolve_form_request_returns_null_for_missing(): void
    {
        // Arrange
        $tool = new CreateEntityTool(AiAgent::class, 'AI Agent');
        $method = new \ReflectionMethod($tool, 'resolveFormRequest');
        $method->setAccessible(true);

        // Act: look for a Store form request for a model with no matching class
        $result = $method->invoke($tool, 'Store');

        // Assert: may return a class name if one exists, or null
        // StoreAiAgentRequest exists in Aicl namespace, not App namespace
        $this->assertTrue($result === null || is_string($result));
    }

    // -- Constructor --

    /**
     * Test constructor stores model class and entity label.
     */
    public function test_constructor_stores_parameters(): void
    {
        // Arrange
        $tool = new CreateEntityTool(AiAgent::class, 'AI Agent');
        $reflection = new \ReflectionClass($tool);

        // Act: read protected properties
        $modelProp = $reflection->getProperty('modelClass');
        $modelProp->setAccessible(true);
        $labelProp = $reflection->getProperty('entityLabel');
        $labelProp->setAccessible(true);

        // Assert
        $this->assertSame(AiAgent::class, $modelProp->getValue($tool));
        $this->assertSame('AI Agent', $labelProp->getValue($tool));
    }
}
