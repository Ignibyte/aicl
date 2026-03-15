<?php

namespace Aicl\Tests\Unit\Mcp\Tools;

use Aicl\Mcp\Tools\CreateEntityTool;
use Aicl\Mcp\Tools\DeleteEntityTool;
use Aicl\Mcp\Tools\TransitionEntityTool;
use Aicl\Mcp\Tools\UpdateEntityTool;
use App\Models\User;
use PHPUnit\Framework\TestCase;

class EntityToolSchemaTest extends TestCase
{
    // ─── CreateEntityTool ──────────────────────────────────

    public function test_create_tool_name(): void
    {
        $tool = new CreateEntityTool(User::class, 'User');

        $this->assertSame('create_user', $tool->name());
    }

    public function test_create_tool_title(): void
    {
        $tool = new CreateEntityTool(User::class, 'User');

        $this->assertSame('Create User', $tool->title());
    }

    public function test_create_tool_schema_includes_fillable_fields(): void
    {
        $tool = new CreateEntityTool(User::class, 'User');
        $array = $tool->toArray();
        $properties = $array['inputSchema']['properties'] ?? [];

        $fillable = (new User)->getFillable();

        foreach ($fillable as $field) {
            $this->assertArrayHasKey($field, $properties, "Schema should include fillable field: {$field}");
        }

        $this->assertCount(count($fillable), $properties);
    }

    public function test_create_tool_description_lists_fillable_fields(): void
    {
        $tool = new CreateEntityTool(User::class, 'User');
        $description = $tool->description();

        $this->assertStringContainsString('User', $description);
        $this->assertStringContainsString('name', $description);
        $this->assertStringContainsString('email', $description);
    }

    // ─── UpdateEntityTool ──────────────────────────────────

    public function test_update_tool_name(): void
    {
        $tool = new UpdateEntityTool(User::class, 'User');

        $this->assertSame('update_user', $tool->name());
    }

    public function test_update_tool_title(): void
    {
        $tool = new UpdateEntityTool(User::class, 'User');

        $this->assertSame('Update User', $tool->title());
    }

    public function test_update_tool_schema_has_required_id_field(): void
    {
        $tool = new UpdateEntityTool(User::class, 'User');
        $array = $tool->toArray();
        $properties = $array['inputSchema']['properties'] ?? [];

        $this->assertArrayHasKey('id', $properties);
    }

    public function test_update_tool_schema_includes_fillable_fields_and_id(): void
    {
        $tool = new UpdateEntityTool(User::class, 'User');
        $array = $tool->toArray();
        $properties = $array['inputSchema']['properties'] ?? [];

        $fillable = (new User)->getFillable();

        // Should have id + all fillable fields
        $this->assertCount(count($fillable) + 1, $properties);

        $this->assertArrayHasKey('id', $properties);
        foreach ($fillable as $field) {
            $this->assertArrayHasKey($field, $properties, "Schema should include fillable field: {$field}");
        }
    }

    public function test_update_tool_description_lists_updatable_fields(): void
    {
        $tool = new UpdateEntityTool(User::class, 'User');
        $description = $tool->description();

        $this->assertStringContainsString('Update', $description);
        $this->assertStringContainsString('User', $description);
        $this->assertStringContainsString('name', $description);
    }

    // ─── DeleteEntityTool ──────────────────────────────────

    public function test_delete_tool_name(): void
    {
        $tool = new DeleteEntityTool(User::class, 'User');

        $this->assertSame('delete_user', $tool->name());
    }

    public function test_delete_tool_title(): void
    {
        $tool = new DeleteEntityTool(User::class, 'User');

        $this->assertSame('Delete User', $tool->title());
    }

    public function test_delete_tool_schema_has_required_id_field(): void
    {
        $tool = new DeleteEntityTool(User::class, 'User');
        $array = $tool->toArray();
        $properties = $array['inputSchema']['properties'] ?? [];

        $this->assertArrayHasKey('id', $properties);
        $this->assertCount(1, $properties);
    }

    public function test_delete_tool_description(): void
    {
        $tool = new DeleteEntityTool(User::class, 'User');
        $description = $tool->description();

        $this->assertStringContainsString('Delete', $description);
        $this->assertStringContainsString('User', $description);
        $this->assertStringContainsString('ID', $description);
    }

    // ─── TransitionEntityTool ──────────────────────────────

    public function test_transition_tool_name(): void
    {
        $tool = new TransitionEntityTool(User::class, 'User');

        $this->assertSame('transition_user', $tool->name());
    }

    public function test_transition_tool_title(): void
    {
        $tool = new TransitionEntityTool(User::class, 'User');

        $this->assertSame('Transition User State', $tool->title());
    }

    public function test_transition_tool_schema_has_id_and_to_fields(): void
    {
        $tool = new TransitionEntityTool(User::class, 'User');
        $array = $tool->toArray();
        $properties = $array['inputSchema']['properties'] ?? [];

        $this->assertArrayHasKey('id', $properties);
        $this->assertArrayHasKey('to', $properties);
    }

    public function test_transition_tool_description_includes_entity_label(): void
    {
        $tool = new TransitionEntityTool(User::class, 'User');
        $description = $tool->description();

        $this->assertStringContainsString('User', $description);
        $this->assertStringContainsString('state', $description);
    }

    // ─── Cross-tool consistency ────────────────────────────

    public function test_all_tools_produce_valid_to_array(): void
    {
        $tools = [
            new CreateEntityTool(User::class, 'User'),
            new UpdateEntityTool(User::class, 'User'),
            new DeleteEntityTool(User::class, 'User'),
            new TransitionEntityTool(User::class, 'User'),
        ];

        foreach ($tools as $tool) {
            $array = $tool->toArray();

            $this->assertArrayHasKey('name', $array, get_class($tool).' should have name');
            $this->assertArrayHasKey('description', $array, get_class($tool).' should have description');
            $this->assertArrayHasKey('inputSchema', $array, get_class($tool).' should have inputSchema');
            $this->assertNotEmpty($array['name'], get_class($tool).' name should not be empty');
        }
    }

    public function test_tool_names_follow_snake_case_convention(): void
    {
        $tools = [
            new CreateEntityTool(User::class, 'User'),
            new UpdateEntityTool(User::class, 'User'),
            new DeleteEntityTool(User::class, 'User'),
            new TransitionEntityTool(User::class, 'User'),
        ];

        foreach ($tools as $tool) {
            $name = $tool->name();
            $this->assertMatchesRegularExpression(
                '/^[a-z_]+$/',
                $name,
                get_class($tool)." name '{$name}' should be snake_case"
            );
        }
    }
}
