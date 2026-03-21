<?php

declare(strict_types=1);

namespace Aicl\Tests\Unit\Console\Support;

use Aicl\Console\Support\FieldParser;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Regression tests for FieldParser PHPStan changes.
 *
 * Tests the (string) cast added to array_shift() in parseSegment()
 * and declare(strict_types=1). Under strict_types, array_shift()
 * returns mixed which needs explicit casting to string.
 */
class FieldParserRegressionTest extends TestCase
{
    /**
     * Test parseSegment casts type argument to string.
     *
     * PHPStan change: (string) array_shift($modifiers) ensures
     * the type argument is always a string, not mixed.
     */
    public function test_parse_enum_field_casts_type_argument_to_string(): void
    {
        // Arrange
        $parser = new FieldParser;

        // Act: parse an enum field
        $fields = $parser->parse('status:enum:TaskStatus');

        // Assert: typeArgument should be string
        $this->assertCount(1, $fields);
        $this->assertSame('TaskStatus', $fields[0]->typeArgument);
        $this->assertIsString($fields[0]->typeArgument);
    }

    /**
     * Test parseSegment casts foreignId table name to string.
     *
     * Same (string) cast pattern for foreignId type arguments.
     */
    public function test_parse_foreign_id_casts_table_name_to_string(): void
    {
        // Arrange
        $parser = new FieldParser;

        // Act: parse a foreignId field
        $fields = $parser->parse('category_id:foreignId:categories');

        // Assert: typeArgument should be string
        $this->assertSame('categories', $fields[0]->typeArgument);
        $this->assertIsString($fields[0]->typeArgument);
    }

    /**
     * Test parse handles multiple fields with mixed types.
     *
     * Verifies that parsing multiple fields works correctly
     * under strict_types with the (string) cast.
     */
    public function test_parse_multiple_fields_with_type_arguments(): void
    {
        // Arrange
        $parser = new FieldParser;

        // Act: parse multiple fields including enum and foreignId
        $fields = $parser->parse('title:string,status:enum:Priority,owner_id:foreignId:users');

        // Assert: all fields should parse correctly
        $this->assertCount(3, $fields);
        $this->assertSame('title', $fields[0]->name);
        $this->assertNull($fields[0]->typeArgument);
        $this->assertSame('Priority', $fields[1]->typeArgument);
        $this->assertSame('users', $fields[2]->typeArgument);
    }

    /**
     * Test parse handles modifiers after type argument.
     *
     * Verifies that nullable/unique modifiers work correctly after
     * the type argument is shifted from the modifiers array.
     */
    public function test_parse_modifiers_after_type_argument(): void
    {
        // Arrange
        $parser = new FieldParser;

        // Act: enum field with nullable modifier after type argument
        $fields = $parser->parse('priority:enum:Priority:nullable');

        // Assert: should have typeArgument and be nullable
        $this->assertSame('Priority', $fields[0]->typeArgument);
        $this->assertTrue($fields[0]->nullable);
    }

    /**
     * Test parse throws for missing type argument on enum.
     *
     * Under strict_types, the validation check must occur before the cast.
     */
    public function test_parse_throws_for_missing_enum_argument(): void
    {
        // Arrange
        $parser = new FieldParser;

        // Assert + Act: should throw InvalidArgumentException
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Enum field');

        $parser->parse('status:enum');
    }

    /**
     * Test parse throws for missing foreignId argument.
     */
    public function test_parse_throws_for_missing_foreign_id_argument(): void
    {
        // Arrange
        $parser = new FieldParser;

        // Assert + Act
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('ForeignId field');

        $parser->parse('category_id:foreignId');
    }

    /**
     * Test parse validates field name format under strict_types.
     */
    public function test_parse_validates_snake_case_name(): void
    {
        // Arrange
        $parser = new FieldParser;

        // Assert + Act: uppercase name should throw
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must be snake_case');

        $parser->parse('BadName:string');
    }
}
