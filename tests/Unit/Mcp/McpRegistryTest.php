<?php

namespace Aicl\Tests\Unit\Mcp;

use Aicl\Mcp\McpRegistry;
use Aicl\Mcp\Prompts\CrudWorkflowPrompt;
use Aicl\Mcp\Prompts\InspectEntityPrompt;
use Aicl\Mcp\Resources\EntityListResource;
use Aicl\Mcp\Tools\ListEntityTool;
use Aicl\Mcp\Tools\ShowEntityTool;
use PHPUnit\Framework\TestCase;

class McpRegistryTest extends TestCase
{
    protected McpRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registry = new McpRegistry;
    }

    public function test_can_register_tool(): void
    {
        $this->registry->registerTool(ListEntityTool::class);

        $this->assertContains(ListEntityTool::class, $this->registry->tools());
    }

    public function test_can_register_resource(): void
    {
        $this->registry->registerResource(EntityListResource::class);

        $this->assertContains(EntityListResource::class, $this->registry->resources());
    }

    public function test_can_register_prompt(): void
    {
        $this->registry->registerPrompt(CrudWorkflowPrompt::class);

        $this->assertContains(CrudWorkflowPrompt::class, $this->registry->prompts());
    }

    public function test_register_many_registers_all_types(): void
    {
        $this->registry->registerMany([
            'tools' => [ListEntityTool::class, ShowEntityTool::class],
            'resources' => [EntityListResource::class],
            'prompts' => [CrudWorkflowPrompt::class, InspectEntityPrompt::class],
        ]);

        $this->assertCount(2, $this->registry->tools());
        $this->assertCount(1, $this->registry->resources());
        $this->assertCount(2, $this->registry->prompts());
    }

    public function test_duplicate_registration_is_ignored(): void
    {
        $this->registry->registerTool(ListEntityTool::class);
        $this->registry->registerTool(ListEntityTool::class);

        $this->assertCount(1, $this->registry->tools());
    }

    public function test_duplicate_resource_registration_is_ignored(): void
    {
        $this->registry->registerResource(EntityListResource::class);
        $this->registry->registerResource(EntityListResource::class);

        $this->assertCount(1, $this->registry->resources());
    }

    public function test_duplicate_prompt_registration_is_ignored(): void
    {
        $this->registry->registerPrompt(CrudWorkflowPrompt::class);
        $this->registry->registerPrompt(CrudWorkflowPrompt::class);

        $this->assertCount(1, $this->registry->prompts());
    }

    public function test_returns_empty_arrays_initially(): void
    {
        $this->assertEmpty($this->registry->tools());
        $this->assertEmpty($this->registry->resources());
        $this->assertEmpty($this->registry->prompts());
    }

    public function test_register_many_with_partial_keys(): void
    {
        $this->registry->registerMany([
            'tools' => [ListEntityTool::class],
        ]);

        $this->assertCount(1, $this->registry->tools());
        $this->assertEmpty($this->registry->resources());
        $this->assertEmpty($this->registry->prompts());
    }

    public function test_register_many_with_empty_array(): void
    {
        $this->registry->registerMany([]);

        $this->assertEmpty($this->registry->tools());
        $this->assertEmpty($this->registry->resources());
        $this->assertEmpty($this->registry->prompts());
    }
}
