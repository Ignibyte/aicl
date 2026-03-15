<?php

namespace Aicl\Tests\Unit\Mcp\Prompts;

use Aicl\Mcp\Prompts\CrudWorkflowPrompt;
use Laravel\Mcp\Server\Prompts\Argument;
use PHPUnit\Framework\TestCase;

class CrudWorkflowPromptTest extends TestCase
{
    protected CrudWorkflowPrompt $prompt;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prompt = new CrudWorkflowPrompt;
    }

    public function test_name_returns_crud_workflow(): void
    {
        $this->assertSame('crud_workflow', $this->prompt->name());
    }

    public function test_description_is_set(): void
    {
        $this->assertStringContainsString('entity', strtolower($this->prompt->description()));
    }

    public function test_arguments_include_entity_type_required(): void
    {
        $arguments = $this->prompt->arguments();

        $this->assertNotEmpty($arguments);

        $entityTypeArg = collect($arguments)->first(fn (Argument $arg): bool => $arg->name === 'entity_type');

        $this->assertNotNull($entityTypeArg);
        $this->assertTrue($entityTypeArg->required);
    }

    public function test_arguments_include_operation_optional(): void
    {
        $arguments = $this->prompt->arguments();

        $operationArg = collect($arguments)->first(fn (Argument $arg): bool => $arg->name === 'operation');

        $this->assertNotNull($operationArg);
        $this->assertFalse($operationArg->required);
    }

    public function test_to_array_includes_arguments(): void
    {
        $array = $this->prompt->toArray();

        $this->assertArrayHasKey('name', $array);
        $this->assertArrayHasKey('arguments', $array);
        $this->assertSame('crud_workflow', $array['name']);
        $this->assertCount(2, $array['arguments']);
    }

    public function test_to_method_call_returns_name(): void
    {
        $methodCall = $this->prompt->toMethodCall();

        $this->assertArrayHasKey('name', $methodCall);
        $this->assertSame('crud_workflow', $methodCall['name']);
    }
}
