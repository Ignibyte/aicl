<?php

namespace Aicl\Tests\Unit\Mcp\Tools;

use Aicl\Mcp\Tools\ShowEntityTool;
use App\Models\User;
use PHPUnit\Framework\TestCase;

class ShowEntityToolTest extends TestCase
{
    protected ShowEntityTool $tool;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tool = new ShowEntityTool(User::class, 'User');
    }

    public function test_name_returns_correct_format(): void
    {
        $this->assertSame('show_user', $this->tool->name());
    }

    public function test_title_returns_formatted_string(): void
    {
        $this->assertSame('Show User', $this->tool->title());
    }

    public function test_description_includes_entity_label(): void
    {
        $description = $this->tool->description();

        $this->assertStringContainsString('User', $description);
        $this->assertStringContainsString('single', $description);
        $this->assertStringContainsString('ID', $description);
    }

    public function test_schema_has_required_id_field(): void
    {
        $array = $this->tool->toArray();
        $properties = $array['inputSchema']['properties'] ?? [];

        $this->assertArrayHasKey('id', $properties);
        $this->assertCount(1, $properties);
    }

    public function test_to_array_includes_input_schema_with_id(): void
    {
        $array = $this->tool->toArray();

        $this->assertArrayHasKey('inputSchema', $array);
        $this->assertSame('show_user', $array['name']);
        /** @phpstan-ignore-next-line */
        $this->assertStringContainsString('User', $array['description']);
    }
}
