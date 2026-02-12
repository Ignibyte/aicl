<?php

namespace Aicl\Tests\Unit\Commands;

use Aicl\Console\Support\FieldDefinition;
use Aicl\Console\Support\FieldParser;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class FieldParserTest extends TestCase
{
    protected FieldParser $parser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->parser = new FieldParser;
    }

    // ========================================================================
    // Basic Parsing
    // ========================================================================

    public function test_parses_simple_string_field(): void
    {
        $fields = $this->parser->parse('title:string');

        $this->assertCount(1, $fields);
        $this->assertEquals('title', $fields[0]->name);
        $this->assertEquals('string', $fields[0]->type);
        $this->assertNull($fields[0]->typeArgument);
        $this->assertFalse($fields[0]->nullable);
        $this->assertFalse($fields[0]->unique);
        $this->assertNull($fields[0]->default);
        $this->assertFalse($fields[0]->indexed);
    }

    public function test_parses_multiple_fields(): void
    {
        $fields = $this->parser->parse('title:string,body:text,count:integer');

        $this->assertCount(3, $fields);
        $this->assertEquals('title', $fields[0]->name);
        $this->assertEquals('body', $fields[1]->name);
        $this->assertEquals('count', $fields[2]->name);
    }

    public function test_returns_empty_array_for_empty_string(): void
    {
        $fields = $this->parser->parse('');

        $this->assertCount(0, $fields);
    }

    public function test_returns_empty_array_for_whitespace(): void
    {
        $fields = $this->parser->parse('   ');

        $this->assertCount(0, $fields);
    }

    public function test_trims_whitespace_around_segments(): void
    {
        $fields = $this->parser->parse(' title:string , body:text ');

        $this->assertCount(2, $fields);
        $this->assertEquals('title', $fields[0]->name);
        $this->assertEquals('body', $fields[1]->name);
    }

    public function test_skips_empty_segments_from_trailing_comma(): void
    {
        $fields = $this->parser->parse('title:string,');

        $this->assertCount(1, $fields);
    }

    // ========================================================================
    // All 10 Field Types
    // ========================================================================

    public function test_parses_string_type(): void
    {
        $fields = $this->parser->parse('name:string');

        $this->assertEquals('string', $fields[0]->type);
        $this->assertFalse($fields[0]->nullable);
    }

    public function test_parses_text_type_defaults_nullable(): void
    {
        $fields = $this->parser->parse('body:text');

        $this->assertEquals('text', $fields[0]->type);
        $this->assertTrue($fields[0]->nullable);
    }

    public function test_parses_integer_type(): void
    {
        $fields = $this->parser->parse('count:integer');

        $this->assertEquals('integer', $fields[0]->type);
        $this->assertFalse($fields[0]->nullable);
    }

    public function test_parses_float_type(): void
    {
        $fields = $this->parser->parse('price:float');

        $this->assertEquals('float', $fields[0]->type);
        $this->assertFalse($fields[0]->nullable);
    }

    public function test_parses_boolean_type_defaults_to_true(): void
    {
        $fields = $this->parser->parse('active:boolean');

        $this->assertEquals('boolean', $fields[0]->type);
        $this->assertEquals('true', $fields[0]->default);
    }

    public function test_parses_date_type_defaults_nullable(): void
    {
        $fields = $this->parser->parse('due_date:date');

        $this->assertEquals('date', $fields[0]->type);
        $this->assertTrue($fields[0]->nullable);
    }

    public function test_parses_datetime_type_defaults_nullable(): void
    {
        $fields = $this->parser->parse('published_at:datetime');

        $this->assertEquals('datetime', $fields[0]->type);
        $this->assertTrue($fields[0]->nullable);
    }

    public function test_parses_enum_type_with_class_name(): void
    {
        $fields = $this->parser->parse('priority:enum:TaskPriority');

        $this->assertEquals('enum', $fields[0]->type);
        $this->assertEquals('TaskPriority', $fields[0]->typeArgument);
        $this->assertTrue($fields[0]->isEnum());
    }

    public function test_parses_json_type_defaults_nullable(): void
    {
        $fields = $this->parser->parse('metadata:json');

        $this->assertEquals('json', $fields[0]->type);
        $this->assertTrue($fields[0]->nullable);
    }

    public function test_parses_foreign_id_type_with_table(): void
    {
        $fields = $this->parser->parse('category_id:foreignId:categories');

        $this->assertEquals('foreignId', $fields[0]->type);
        $this->assertEquals('categories', $fields[0]->typeArgument);
        $this->assertTrue($fields[0]->isForeignKey());
    }

    // ========================================================================
    // Modifiers
    // ========================================================================

    public function test_nullable_modifier(): void
    {
        $fields = $this->parser->parse('title:string:nullable');

        $this->assertTrue($fields[0]->nullable);
    }

    public function test_unique_modifier(): void
    {
        $fields = $this->parser->parse('slug:string:unique');

        $this->assertTrue($fields[0]->unique);
    }

    public function test_index_modifier(): void
    {
        $fields = $this->parser->parse('code:string:index');

        $this->assertTrue($fields[0]->indexed);
    }

    public function test_default_modifier(): void
    {
        $fields = $this->parser->parse('priority:string:default(normal)');

        $this->assertEquals('normal', $fields[0]->default);
    }

    public function test_multiple_modifiers(): void
    {
        $fields = $this->parser->parse('slug:string:nullable:unique:index');

        $this->assertTrue($fields[0]->nullable);
        $this->assertTrue($fields[0]->unique);
        $this->assertTrue($fields[0]->indexed);
    }

    public function test_enum_with_modifier_after_class(): void
    {
        $fields = $this->parser->parse('priority:enum:TaskPriority:nullable');

        $this->assertEquals('TaskPriority', $fields[0]->typeArgument);
        $this->assertTrue($fields[0]->nullable);
    }

    public function test_foreign_id_with_modifier_after_table(): void
    {
        $fields = $this->parser->parse('category_id:foreignId:categories:nullable');

        $this->assertEquals('categories', $fields[0]->typeArgument);
        $this->assertTrue($fields[0]->nullable);
    }

    // ========================================================================
    // Type-Specific Defaults
    // ========================================================================

    public function test_text_is_auto_nullable(): void
    {
        $fields = $this->parser->parse('body:text');

        $this->assertTrue($fields[0]->nullable);
    }

    public function test_date_is_auto_nullable(): void
    {
        $fields = $this->parser->parse('start:date');

        $this->assertTrue($fields[0]->nullable);
    }

    public function test_datetime_is_auto_nullable(): void
    {
        $fields = $this->parser->parse('start_at:datetime');

        $this->assertTrue($fields[0]->nullable);
    }

    public function test_json_is_auto_nullable(): void
    {
        $fields = $this->parser->parse('data:json');

        $this->assertTrue($fields[0]->nullable);
    }

    public function test_boolean_defaults_to_true(): void
    {
        $fields = $this->parser->parse('active:boolean');

        $this->assertEquals('true', $fields[0]->default);
    }

    public function test_boolean_default_can_be_overridden(): void
    {
        $fields = $this->parser->parse('active:boolean:default(false)');

        $this->assertEquals('false', $fields[0]->default);
    }

    // ========================================================================
    // FieldDefinition Helper Methods
    // ========================================================================

    public function test_is_foreign_key(): void
    {
        $fields = $this->parser->parse('user_id:foreignId:users');

        $this->assertTrue($fields[0]->isForeignKey());
    }

    public function test_is_not_foreign_key(): void
    {
        $fields = $this->parser->parse('title:string');

        $this->assertFalse($fields[0]->isForeignKey());
    }

    public function test_is_enum(): void
    {
        $fields = $this->parser->parse('status:enum:Status');

        $this->assertTrue($fields[0]->isEnum());
    }

    public function test_is_not_enum(): void
    {
        $fields = $this->parser->parse('title:string');

        $this->assertFalse($fields[0]->isEnum());
    }

    public function test_relationship_method_name_strips_id(): void
    {
        $fields = $this->parser->parse('category_id:foreignId:categories');

        $this->assertEquals('category', $fields[0]->relationshipMethodName());
    }

    public function test_relationship_method_name_camel_case(): void
    {
        $fields = $this->parser->parse('assigned_to:foreignId:users');

        $this->assertEquals('assignedTo', $fields[0]->relationshipMethodName());
    }

    public function test_relationship_method_name_null_for_non_fk(): void
    {
        $fields = $this->parser->parse('title:string');

        $this->assertNull($fields[0]->relationshipMethodName());
    }

    public function test_related_model_name_from_table(): void
    {
        $fields = $this->parser->parse('category_id:foreignId:categories');

        $this->assertEquals('Category', $fields[0]->relatedModelName());
    }

    public function test_related_model_name_from_users_table(): void
    {
        $fields = $this->parser->parse('owner_id:foreignId:users');

        $this->assertEquals('User', $fields[0]->relatedModelName());
    }

    public function test_related_model_name_null_for_non_fk(): void
    {
        $fields = $this->parser->parse('title:string');

        $this->assertNull($fields[0]->relatedModelName());
    }

    // ========================================================================
    // Validation Errors
    // ========================================================================

    public function test_rejects_missing_type(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Expected format');

        $this->parser->parse('title');
    }

    public function test_rejects_non_snake_case_name(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must be snake_case');

        $this->parser->parse('myTitle:string');
    }

    public function test_rejects_name_starting_with_number(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must be snake_case');

        $this->parser->parse('1title:string');
    }

    public function test_rejects_reserved_column_id(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('reserved');

        $this->parser->parse('id:integer');
    }

    public function test_rejects_reserved_column_created_at(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('reserved');

        $this->parser->parse('created_at:datetime');
    }

    public function test_rejects_reserved_column_updated_at(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('reserved');

        $this->parser->parse('updated_at:datetime');
    }

    public function test_rejects_reserved_column_deleted_at(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('reserved');

        $this->parser->parse('deleted_at:datetime');
    }

    public function test_rejects_duplicate_field_names(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Duplicate');

        $this->parser->parse('title:string,title:text');
    }

    public function test_rejects_unknown_type(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown field type');

        $this->parser->parse('title:varchar');
    }

    public function test_rejects_enum_without_class_name(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('requires an argument');

        $this->parser->parse('priority:enum');
    }

    public function test_rejects_foreign_id_without_table(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('requires an argument');

        $this->parser->parse('user_id:foreignId');
    }

    public function test_rejects_enum_with_non_pascal_case_class(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('PascalCase');

        $this->parser->parse('priority:enum:task_priority');
    }

    public function test_rejects_foreign_id_with_non_snake_case_table(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('snake_case');

        $this->parser->parse('user_id:foreignId:Users');
    }

    public function test_rejects_unknown_modifier(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown modifier');

        $this->parser->parse('title:string:required');
    }

    public function test_collects_multiple_errors(): void
    {
        try {
            $this->parser->parse('id:integer,1bad:string,title:varchar');
            $this->fail('Expected InvalidArgumentException');
        } catch (InvalidArgumentException $e) {
            $message = $e->getMessage();
            $this->assertStringContainsString('reserved', $message);
            $this->assertStringContainsString('snake_case', $message);
            $this->assertStringContainsString('Unknown field type', $message);
        }
    }

    // ========================================================================
    // Returns FieldDefinition Instances
    // ========================================================================

    public function test_returns_field_definition_instances(): void
    {
        $fields = $this->parser->parse('title:string');

        $this->assertInstanceOf(FieldDefinition::class, $fields[0]);
    }

    // ========================================================================
    // Complex Realistic Scenarios
    // ========================================================================

    public function test_parses_full_entity_spec(): void
    {
        $fields = $this->parser->parse(
            'title:string,description:text:nullable,priority:enum:TaskPriority,due_date:date:nullable,budget:float:nullable,assigned_to:foreignId:users,is_published:boolean'
        );

        $this->assertCount(7, $fields);

        // title
        $this->assertEquals('string', $fields[0]->type);
        $this->assertFalse($fields[0]->nullable);

        // description (text auto-nullable + explicit nullable = nullable)
        $this->assertEquals('text', $fields[1]->type);
        $this->assertTrue($fields[1]->nullable);

        // priority enum
        $this->assertEquals('enum', $fields[2]->type);
        $this->assertEquals('TaskPriority', $fields[2]->typeArgument);

        // due_date (date auto-nullable + explicit = nullable)
        $this->assertEquals('date', $fields[3]->type);
        $this->assertTrue($fields[3]->nullable);

        // budget float nullable
        $this->assertEquals('float', $fields[4]->type);
        $this->assertTrue($fields[4]->nullable);

        // assigned_to foreignId
        $this->assertEquals('foreignId', $fields[5]->type);
        $this->assertEquals('users', $fields[5]->typeArgument);
        $this->assertEquals('assignedTo', $fields[5]->relationshipMethodName());
        $this->assertEquals('User', $fields[5]->relatedModelName());

        // is_published boolean
        $this->assertEquals('boolean', $fields[6]->type);
        $this->assertEquals('true', $fields[6]->default);
    }
}
