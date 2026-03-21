<?php

namespace Aicl\Tests\Unit\Mcp\Resources;

use Aicl\Mcp\Resources\EntityListResource;
use Laravel\Mcp\Server\Contracts\HasUriTemplate;
use Laravel\Mcp\Support\UriTemplate;
use PHPUnit\Framework\TestCase;

class EntityListResourceTest extends TestCase
{
    protected EntityListResource $resource;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resource = new EntityListResource;
    }

    public function test_implements_has_uri_template(): void
    {
        $this->assertInstanceOf(HasUriTemplate::class, $this->resource);
    }

    public function test_uri_template_returns_correct_pattern(): void
    {
        $template = $this->resource->uriTemplate();

        $this->assertInstanceOf(UriTemplate::class, $template);
        $this->assertSame('entity://{type}', (string) $template);
    }

    public function test_name_returns_entity_list(): void
    {
        $this->assertSame('entity_list', $this->resource->name());
    }

    public function test_mime_type_is_application_json(): void
    {
        $this->assertSame('application/json', $this->resource->mimeType());
    }

    public function test_to_array_includes_uri_template(): void
    {
        $array = $this->resource->toArray();

        $this->assertArrayHasKey('uriTemplate', $array);
        /** @phpstan-ignore-next-line */
        $this->assertSame('entity://{type}', $array['uriTemplate']);
        $this->assertArrayNotHasKey('uri', $array);
    }

    public function test_description_mentions_entity_types(): void
    {
        $description = $this->resource->description();

        $this->assertStringContainsString('entity', strtolower($description));
    }
}
