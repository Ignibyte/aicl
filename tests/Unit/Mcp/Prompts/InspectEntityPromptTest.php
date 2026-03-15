<?php

namespace Aicl\Tests\Unit\Mcp\Prompts;

use Aicl\Mcp\Prompts\InspectEntityPrompt;
use Laravel\Mcp\Server\Prompts\Argument;
use PHPUnit\Framework\TestCase;

class InspectEntityPromptTest extends TestCase
{
    protected InspectEntityPrompt $prompt;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prompt = new InspectEntityPrompt;
    }

    public function test_name_returns_inspect_entity(): void
    {
        $this->assertSame('inspect_entity', $this->prompt->name());
    }

    public function test_description_mentions_inspect(): void
    {
        $this->assertStringContainsString('Inspect', $this->prompt->description());
    }

    public function test_arguments_include_entity_type_required(): void
    {
        $arguments = $this->prompt->arguments();

        $entityTypeArg = collect($arguments)->first(fn (Argument $arg): bool => $arg->name === 'entity_type');

        $this->assertNotNull($entityTypeArg);
        $this->assertTrue($entityTypeArg->required);
    }

    public function test_arguments_include_entity_id_required(): void
    {
        $arguments = $this->prompt->arguments();

        $entityIdArg = collect($arguments)->first(fn (Argument $arg): bool => $arg->name === 'entity_id');

        $this->assertNotNull($entityIdArg);
        $this->assertTrue($entityIdArg->required);
    }

    public function test_has_two_arguments(): void
    {
        $this->assertCount(2, $this->prompt->arguments());
    }

    public function test_to_array_includes_arguments(): void
    {
        $array = $this->prompt->toArray();

        $this->assertArrayHasKey('name', $array);
        $this->assertArrayHasKey('arguments', $array);
        $this->assertSame('inspect_entity', $array['name']);
    }
}
