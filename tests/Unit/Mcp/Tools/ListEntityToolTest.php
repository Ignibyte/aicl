<?php

namespace Aicl\Tests\Unit\Mcp\Tools;

use Aicl\Mcp\Tools\ListEntityTool;
use App\Models\User;
use PHPUnit\Framework\TestCase;

class ListEntityToolTest extends TestCase
{
    protected ListEntityTool $tool;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tool = new ListEntityTool(User::class, 'User');
    }

    public function test_name_returns_snake_plural_format(): void
    {
        $this->assertSame('list_users', $this->tool->name());
    }

    public function test_name_handles_multi_word_entity(): void
    {
        // Simulate a multi-word model class
        $tool = new ListEntityTool(User::class, 'Blog Post');

        // class_basename(User::class) = 'User' -> snake_plural = 'users'
        $this->assertSame('list_users', $tool->name());
    }

    public function test_title_returns_formatted_string(): void
    {
        $this->assertSame('List User Records', $this->tool->title());
    }

    public function test_description_includes_entity_label(): void
    {
        $description = $this->tool->description();

        $this->assertStringContainsString('User', $description);
        $this->assertStringContainsString('pagination', $description);
        $this->assertStringContainsString('search', $description);
        $this->assertStringContainsString('sorting', $description);
    }

    public function test_schema_returns_correct_structure_via_to_array(): void
    {
        $array = $this->tool->toArray();
        $properties = $array['inputSchema']['properties'] ?? [];

        $this->assertArrayHasKey('page', $properties);
        $this->assertArrayHasKey('per_page', $properties);
        $this->assertArrayHasKey('search', $properties);
        $this->assertArrayHasKey('sort_by', $properties);
        $this->assertArrayHasKey('sort_dir', $properties);

        // Should have exactly 5 properties
        $this->assertCount(5, $properties);
    }

    public function test_to_array_includes_input_schema(): void
    {
        $array = $this->tool->toArray();

        $this->assertArrayHasKey('name', $array);
        $this->assertArrayHasKey('description', $array);
        $this->assertArrayHasKey('inputSchema', $array);
        $this->assertSame('list_users', $array['name']);
    }
}
