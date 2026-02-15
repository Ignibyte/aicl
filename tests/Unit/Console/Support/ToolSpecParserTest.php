<?php

namespace Aicl\Tests\Unit\Console\Support;

use Aicl\Console\Support\ToolSpecParser;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ToolSpecParserTest extends TestCase
{
    protected ToolSpecParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new ToolSpecParser;
    }

    // ========================================================================
    // Full parse
    // ========================================================================

    public function test_parse_complete_tool_spec(): void
    {
        $content = <<<'MD'
# ProjectSummary

A tool that provides a summary of project statistics and health.

## Tool

| Field | Value |
|-------|-------|
| Name | project_summary |
| Category | queries |
| Auth Required | true |
| Description | Get a summary of project statistics including counts by status. |

## Parameters

| Name | Type | Required | Description |
|------|------|----------|-------------|
| status_filter | string | false | Filter by status |
| include_budget | boolean | false | Include budget utilization data |

## Returns

| Field | Type | Description |
|-------|------|-------------|
| total | integer | Total project count |
| by_status | object | Count per status |
| overdue_count | integer | Projects past deadline |
MD;

        $spec = $this->parser->parseContent($content);

        $this->assertSame('ProjectSummary', $spec->className);
        $this->assertStringContainsString('summary of project statistics', $spec->description);
        $this->assertSame('project_summary', $spec->name);
        $this->assertSame('queries', $spec->category);
        $this->assertTrue($spec->authRequired);
        $this->assertStringContainsString('counts by status', $spec->toolDescription);

        $this->assertTrue($spec->hasParameters());
        $this->assertCount(2, $spec->parameters);
        $this->assertSame('status_filter', $spec->parameters[0]->name);
        $this->assertSame('string', $spec->parameters[0]->type);
        $this->assertFalse($spec->parameters[0]->required);
        $this->assertSame('include_budget', $spec->parameters[1]->name);
        $this->assertSame('boolean', $spec->parameters[1]->type);

        $this->assertTrue($spec->hasReturns());
        $this->assertCount(3, $spec->returns);
        $this->assertSame('total', $spec->returns[0]->field);
        $this->assertSame('integer', $spec->returns[0]->type);
    }

    public function test_parse_minimal_tool_spec(): void
    {
        $content = <<<'MD'
# WhosOnline

List currently online users.

## Tool

| Field | Value |
|-------|-------|
| Name | whos_online |
| Description | Get a list of currently online users. |
MD;

        $spec = $this->parser->parseContent($content);

        $this->assertSame('WhosOnline', $spec->className);
        $this->assertSame('whos_online', $spec->name);
        $this->assertSame('general', $spec->category);
        $this->assertFalse($spec->authRequired);
        $this->assertFalse($spec->hasParameters());
        $this->assertFalse($spec->hasReturns());
    }

    public function test_parse_tool_with_required_parameters(): void
    {
        $content = <<<'MD'
# QueryEntity

Query entities by type.

## Tool

| Field | Value |
|-------|-------|
| Name | query_entity |
| Category | queries |
| Auth Required | true |
| Description | Query database entities. |

## Parameters

| Name | Type | Required | Description |
|------|------|----------|-------------|
| entity_type | string | true | The entity type to query |
| limit | integer | false | Max records to return |
MD;

        $spec = $this->parser->parseContent($content);

        $this->assertCount(2, $spec->parameters);
        $this->assertTrue($spec->parameters[0]->required);
        $this->assertFalse($spec->parameters[1]->required);

        $required = $spec->requiredParameters();
        $this->assertCount(1, $required);
        $this->assertSame('entity_type', $required[0]->name);

        $optional = $spec->optionalParameters();
        $this->assertCount(1, $optional);
        $this->assertSame('limit', $optional[0]->name);
    }

    // ========================================================================
    // ToolParameterSpec type mapping
    // ========================================================================

    public function test_parameter_neuron_ai_type_mapping(): void
    {
        $content = <<<'MD'
# TypeTest

Test type mapping.

## Tool

| Field | Value |
|-------|-------|
| Name | type_test |
| Description | Test. |

## Parameters

| Name | Type | Required | Description |
|------|------|----------|-------------|
| p1 | string | false | String param |
| p2 | integer | false | Integer param |
| p3 | boolean | false | Boolean param |
| p4 | number | false | Number param |
| p5 | array | false | Array param |
MD;

        $spec = $this->parser->parseContent($content);

        $this->assertSame('PropertyType::STRING', $spec->parameters[0]->neuronAiType());
        $this->assertSame('PropertyType::INTEGER', $spec->parameters[1]->neuronAiType());
        $this->assertSame('PropertyType::BOOLEAN', $spec->parameters[2]->neuronAiType());
        $this->assertSame('PropertyType::NUMBER', $spec->parameters[3]->neuronAiType());
        $this->assertSame('PropertyType::ARRAY', $spec->parameters[4]->neuronAiType());
    }

    public function test_parameter_php_type_mapping(): void
    {
        $content = <<<'MD'
# PhpTypeTest

Test PHP types.

## Tool

| Field | Value |
|-------|-------|
| Name | php_type_test |
| Description | Test. |

## Parameters

| Name | Type | Required | Description |
|------|------|----------|-------------|
| p1 | string | false | String |
| p2 | int | false | Int shorthand |
| p3 | bool | false | Bool shorthand |
| p4 | float | false | Float |
MD;

        $spec = $this->parser->parseContent($content);

        $this->assertSame('string', $spec->parameters[0]->phpType());
        $this->assertSame('int', $spec->parameters[1]->phpType());
        $this->assertSame('bool', $spec->parameters[2]->phpType());
        $this->assertSame('float', $spec->parameters[3]->phpType());
    }

    // ========================================================================
    // Error cases
    // ========================================================================

    public function test_missing_name_header_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must start with a # ClassName header');

        $this->parser->parseContent('No header here.');
    }

    public function test_missing_tool_section_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must have a ## Tool section');

        $content = <<<'MD'
# MyTool

A tool.
MD;

        $this->parser->parseContent($content);
    }

    public function test_missing_tool_name_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must have a Name field');

        $content = <<<'MD'
# MyTool

A tool.

## Tool

| Field | Value |
|-------|-------|
| Category | queries |
MD;

        $this->parser->parseContent($content);
    }

    public function test_non_pascal_case_class_name_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must be PascalCase');

        $content = <<<'MD'
# my_tool

A tool.

## Tool

| Field | Value |
|-------|-------|
| Name | my_tool |
MD;

        $this->parser->parseContent($content);
    }

    // ========================================================================
    // Edge cases
    // ========================================================================

    public function test_parameter_with_empty_name_is_skipped(): void
    {
        $content = <<<'MD'
# TestTool

A tool.

## Tool

| Field | Value |
|-------|-------|
| Name | test_tool |
| Description | Test. |

## Parameters

| Name | Type | Required | Description |
|------|------|----------|-------------|
| valid | string | false | A valid param |
| | string | false | Empty name param |
MD;

        $spec = $this->parser->parseContent($content);

        $this->assertCount(1, $spec->parameters);
        $this->assertSame('valid', $spec->parameters[0]->name);
    }

    public function test_return_field_with_empty_field_is_skipped(): void
    {
        $content = <<<'MD'
# TestTool

A tool.

## Tool

| Field | Value |
|-------|-------|
| Name | test_tool |
| Description | Test. |

## Returns

| Field | Type | Description |
|-------|------|-------------|
| total | integer | Total count |
| | string | Empty field |
MD;

        $spec = $this->parser->parseContent($content);

        $this->assertCount(1, $spec->returns);
        $this->assertSame('total', $spec->returns[0]->field);
    }
}
