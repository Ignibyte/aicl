<?php

namespace Aicl\Tests\Unit\Mcp\Resources;

use Aicl\Mcp\Resources\EntitySchemaResource;
use App\Models\User;
use PHPUnit\Framework\TestCase;

class EntitySchemaResourceTest extends TestCase
{
    protected EntitySchemaResource $resource;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resource = new EntitySchemaResource(User::class, 'User');
    }

    public function test_uri_returns_correct_format(): void
    {
        $this->assertSame('entity://user/schema', $this->resource->uri());
    }

    public function test_uri_handles_multi_word_entity(): void
    {
        // User class basename is "User" -> snake = "user"
        $resource = new EntitySchemaResource(User::class, 'Blog Post');

        $this->assertSame('entity://user/schema', $resource->uri());
    }

    public function test_mime_type_is_application_json(): void
    {
        $this->assertSame('application/json', $this->resource->mimeType());
    }

    public function test_name_returns_snake_schema_format(): void
    {
        $this->assertSame('user_schema', $this->resource->name());
    }

    public function test_title_includes_entity_label(): void
    {
        $this->assertSame('User Schema', $this->resource->title());
    }

    public function test_description_includes_entity_label(): void
    {
        $description = $this->resource->description();

        $this->assertStringContainsString('User', $description);
        $this->assertStringContainsString('schema', strtolower($description));
    }

    public function test_to_array_includes_uri_and_mime_type(): void
    {
        $array = $this->resource->toArray();

        $this->assertArrayHasKey('name', $array);
        $this->assertArrayHasKey('uri', $array);
        $this->assertArrayHasKey('mimeType', $array);
        /** @phpstan-ignore-next-line */
        $this->assertSame('entity://user/schema', $array['uri']);
        $this->assertSame('application/json', $array['mimeType']);
    }

    public function test_to_method_call_returns_uri(): void
    {
        $methodCall = $this->resource->toMethodCall();

        $this->assertArrayHasKey('uri', $methodCall);
        $this->assertSame('entity://user/schema', $methodCall['uri']);
    }
}
