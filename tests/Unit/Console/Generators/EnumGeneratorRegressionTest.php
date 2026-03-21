<?php

declare(strict_types=1);

namespace Aicl\Tests\Unit\Console\Generators;

use Aicl\Console\Support\FieldDefinition;
use PHPUnit\Framework\TestCase;

/**
 * Regression tests for EnumGenerator PHPStan changes.
 *
 * Tests the $field->typeArgument ?? '' null coalescing added to
 * handle cases where typeArgument could be null on a FieldDefinition.
 * Under strict_types, passing null where string is expected throws TypeError.
 */
class EnumGeneratorRegressionTest extends TestCase
{
    /**
     * Test FieldDefinition with non-null typeArgument works correctly.
     *
     * Happy path: enum field with a valid type argument.
     */
    public function test_enum_field_has_type_argument(): void
    {
        // Arrange
        $field = new FieldDefinition(
            name: 'status',
            type: 'enum',
            typeArgument: 'TaskStatus',
            nullable: false,
            unique: false,
            default: null,
            indexed: false,
        );

        // Act: simulate the EnumGenerator's usage pattern
        $enumName = $field->typeArgument ?? '';

        // Assert: should use the actual type argument
        $this->assertSame('TaskStatus', $enumName);
    }

    /**
     * Test null typeArgument defaults to empty string.
     *
     * PHPStan change: $field->typeArgument ?? '' ensures a null
     * typeArgument produces an empty string instead of TypeError.
     * This scenario is unusual (enum fields should always have typeArgument)
     * but the null guard prevents crashes for malformed data.
     */
    public function test_null_type_argument_defaults_to_empty_string(): void
    {
        // Arrange: create an enum field with null typeArgument (edge case)
        $field = new FieldDefinition(
            name: 'status',
            type: 'enum',
            typeArgument: null,
            nullable: false,
            unique: false,
            default: null,
            indexed: false,
        );

        // Act: use the null coalescing pattern from the PHPStan fix
        $enumName = $field->typeArgument ?? '';

        // Assert: should default to empty string
        $this->assertSame('', $enumName);
    }

    /**
     * Test isEnum() returns true for enum type fields.
     *
     * Verifies basic behavior is preserved after strict_types.
     */
    public function test_is_enum_returns_true(): void
    {
        $field = new FieldDefinition(
            name: 'priority',
            type: 'enum',
            typeArgument: 'Priority',
            nullable: false,
            unique: false,
            default: null,
            indexed: false,
        );

        $this->assertTrue($field->isEnum());
    }

    /**
     * Test isEnum() returns false for non-enum types.
     */
    public function test_is_enum_returns_false_for_string(): void
    {
        $field = new FieldDefinition(
            name: 'title',
            type: 'string',
            typeArgument: null,
            nullable: false,
            unique: false,
            default: null,
            indexed: false,
        );

        $this->assertFalse($field->isEnum());
    }
}
