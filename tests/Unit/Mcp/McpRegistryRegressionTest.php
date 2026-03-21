<?php

declare(strict_types=1);

namespace Aicl\Tests\Unit\Mcp;

use Aicl\Mcp\McpRegistry;
use Aicl\Mcp\Prompts\CrudWorkflowPrompt;
use Aicl\Mcp\Prompts\InspectEntityPrompt;
use Aicl\Mcp\Resources\EntityListResource;
use Aicl\Mcp\Resources\EntitySchemaResource;
use Aicl\Mcp\Tools\ListEntityTool;
use Aicl\Mcp\Tools\ShowEntityTool;
use PHPUnit\Framework\TestCase;

/**
 * Regression tests for McpRegistry PHPStan changes.
 *
 * Covers the registry's ability to accept and return class-string typed
 * tools, resources, and prompts. The registry stores class names (strings),
 * not instances, which are resolved from the container at boot time.
 */
class McpRegistryRegressionTest extends TestCase
{
    // -- Tool registration --

    /**
     * Test tools() returns empty array by default.
     *
     * Fresh registry should have no tools registered.
     */
    public function test_tools_returns_empty_by_default(): void
    {
        // Arrange
        $registry = new McpRegistry;

        // Act
        $tools = $registry->tools();

        // Assert
        $this->assertSame([], $tools);
    }

    /**
     * Test registerTool adds a class-string to the tool collection.
     *
     * The registry stores class names as strings, not instances.
     */
    public function test_register_tool_adds_class_string(): void
    {
        // Arrange
        $registry = new McpRegistry;

        // Act: register a tool class name
        $registry->registerTool(ListEntityTool::class);
        $tools = $registry->tools();

        // Assert
        $this->assertCount(1, $tools);
        $this->assertSame(ListEntityTool::class, $tools[0]);
    }

    // -- Resource registration --

    /**
     * Test resources() returns empty array by default.
     */
    public function test_resources_returns_empty_by_default(): void
    {
        // Arrange
        $registry = new McpRegistry;

        // Act
        $resources = $registry->resources();

        // Assert
        $this->assertSame([], $resources);
    }

    /**
     * Test registerResource adds a class-string to the resource collection.
     */
    public function test_register_resource_adds_class_string(): void
    {
        // Arrange
        $registry = new McpRegistry;

        // Act
        $registry->registerResource(EntityListResource::class);
        $resources = $registry->resources();

        // Assert
        $this->assertCount(1, $resources);
        $this->assertSame(EntityListResource::class, $resources[0]);
    }

    // -- Prompt registration --

    /**
     * Test prompts() returns empty array by default.
     */
    public function test_prompts_returns_empty_by_default(): void
    {
        // Arrange
        $registry = new McpRegistry;

        // Act
        $prompts = $registry->prompts();

        // Assert
        $this->assertSame([], $prompts);
    }

    /**
     * Test registerPrompt adds a class-string to the prompt collection.
     */
    public function test_register_prompt_adds_class_string(): void
    {
        // Arrange
        $registry = new McpRegistry;

        // Act
        $registry->registerPrompt(CrudWorkflowPrompt::class);
        $prompts = $registry->prompts();

        // Assert
        $this->assertCount(1, $prompts);
        $this->assertSame(CrudWorkflowPrompt::class, $prompts[0]);
    }

    // -- Multiple registrations --

    /**
     * Test multiple tools can be registered.
     */
    public function test_multiple_tools_registered(): void
    {
        // Arrange
        $registry = new McpRegistry;

        // Act: register two distinct tool classes
        $registry->registerTool(ListEntityTool::class);
        $registry->registerTool(ShowEntityTool::class);

        // Assert
        $this->assertCount(2, $registry->tools());
    }

    // -- Deduplication --

    /**
     * Test duplicate tool registrations are ignored.
     *
     * The registry uses in_array() to prevent duplicate class names.
     */
    public function test_duplicate_tools_are_deduplicated(): void
    {
        // Arrange
        $registry = new McpRegistry;

        // Act: register the same class twice
        $registry->registerTool(ListEntityTool::class);
        $registry->registerTool(ListEntityTool::class);

        // Assert: only one entry
        $this->assertCount(1, $registry->tools());
    }

    // -- registerMany --

    /**
     * Test registerMany registers tools, resources, and prompts at once.
     */
    public function test_register_many_registers_all_types(): void
    {
        // Arrange
        $registry = new McpRegistry;

        // Act
        $registry->registerMany([
            'tools' => [ListEntityTool::class],
            'resources' => [EntitySchemaResource::class],
            'prompts' => [InspectEntityPrompt::class],
        ]);

        // Assert
        $this->assertCount(1, $registry->tools());
        $this->assertCount(1, $registry->resources());
        $this->assertCount(1, $registry->prompts());
    }

    /**
     * Test registerMany handles empty/missing keys.
     */
    public function test_register_many_handles_empty_keys(): void
    {
        // Arrange
        $registry = new McpRegistry;

        // Act: pass empty array
        $registry->registerMany([]);

        // Assert: all collections remain empty
        $this->assertSame([], $registry->tools());
        $this->assertSame([], $registry->resources());
        $this->assertSame([], $registry->prompts());
    }
}
