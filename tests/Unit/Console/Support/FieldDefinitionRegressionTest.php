<?php

declare(strict_types=1);

namespace Aicl\Tests\Unit\Console\Support;

use Aicl\Console\Support\FieldDefinition;
use PHPUnit\Framework\TestCase;

/**
 * Regression tests for FieldDefinition PHPStan changes.
 *
 * Tests the declare(strict_types=1) addition and the preg_replace() ?? $name
 * null coalescing in the label() and relationshipMethodName() methods.
 */
class FieldDefinitionRegressionTest extends TestCase
{
    /**
     * Test label() strips _id suffix from foreign key field names.
     *
     * PHPStan change: preg_replace() return now uses ?? $name fallback
     * for null safety under strict_types.
     */
    public function test_label_strips_id_suffix_from_foreign_key(): void
    {
        // Arrange: create a foreign key field
        $field = new FieldDefinition(
            name: 'category_id',
            type: 'foreignId',
            typeArgument: 'categories',
            nullable: false,
            unique: false,
            default: null,
            indexed: false,
        );

        // Act
        $label = $field->label();

        // Assert: should strip _id and title-case
        $this->assertSame('Category', $label);
    }

    /**
     * Test label() handles compound foreign key names.
     *
     * Verifies the preg_replace works with multi-word names.
     */
    public function test_label_handles_compound_foreign_key_name(): void
    {
        // Arrange
        $field = new FieldDefinition(
            name: 'assigned_user_id',
            type: 'foreignId',
            typeArgument: 'users',
            nullable: true,
            unique: false,
            default: null,
            indexed: false,
        );

        // Act
        $label = $field->label();

        // Assert: should produce "Assigned User" (not "Assigned User Id")
        $this->assertSame('Assigned User', $label);
    }

    /**
     * Test label() returns title-cased name for non-FK fields.
     *
     * Non-FK fields should not have _id stripping applied.
     */
    public function test_label_returns_title_case_for_non_fk_field(): void
    {
        // Arrange
        $field = new FieldDefinition(
            name: 'display_name',
            type: 'string',
            typeArgument: null,
            nullable: false,
            unique: false,
            default: null,
            indexed: false,
        );

        // Act
        $label = $field->label();

        // Assert
        $this->assertSame('Display Name', $label);
    }

    /**
     * Test relationshipMethodName() returns camelCase name for FK fields.
     *
     * PHPStan change: preg_replace() ?? $this->name ensures null safety.
     */
    public function test_relationship_method_name_returns_camel_case(): void
    {
        // Arrange
        $field = new FieldDefinition(
            name: 'owner_id',
            type: 'foreignId',
            typeArgument: 'users',
            nullable: false,
            unique: false,
            default: null,
            indexed: false,
        );

        // Act
        $result = $field->relationshipMethodName();

        // Assert: should return camelCase without _id suffix
        $this->assertSame('owner', $result);
    }

    /**
     * Test relationshipMethodName() returns null for non-FK fields.
     *
     * Non-FK fields should not generate relationship method names.
     */
    public function test_relationship_method_name_returns_null_for_non_fk(): void
    {
        // Arrange
        $field = new FieldDefinition(
            name: 'title',
            type: 'string',
            typeArgument: null,
            nullable: false,
            unique: false,
            default: null,
            indexed: false,
        );

        // Act
        $result = $field->relationshipMethodName();

        // Assert
        $this->assertNull($result);
    }

    /**
     * Test relatedModelName() returns studly-cased singular table name.
     */
    public function test_related_model_name_returns_studly_singular(): void
    {
        // Arrange
        $field = new FieldDefinition(
            name: 'category_id',
            type: 'foreignId',
            typeArgument: 'categories',
            nullable: false,
            unique: false,
            default: null,
            indexed: false,
        );

        // Act
        $result = $field->relatedModelName();

        // Assert: categories -> Category
        $this->assertSame('Category', $result);
    }

    /**
     * Test relatedModelName() returns null when typeArgument is null.
     *
     * Edge case: FK field without a table name specified.
     */
    public function test_related_model_name_returns_null_without_type_argument(): void
    {
        // Arrange
        $field = new FieldDefinition(
            name: 'parent_id',
            type: 'foreignId',
            typeArgument: null,
            nullable: true,
            unique: false,
            default: null,
            indexed: false,
        );

        // Act
        $result = $field->relatedModelName();

        // Assert
        $this->assertNull($result);
    }

    /**
     * Test fromBaseSchema() creates FieldDefinition with correct defaults.
     *
     * Verifies the factory method works under strict_types.
     */
    public function test_from_base_schema_creates_definition(): void
    {
        // Arrange
        $column = [
            'name' => 'description',
            'type' => 'text',
            'modifiers' => ['nullable'],
        ];

        // Act
        $field = FieldDefinition::fromBaseSchema($column);

        // Assert
        $this->assertSame('description', $field->name);
        $this->assertSame('text', $field->type);
        $this->assertTrue($field->nullable);
        $this->assertNull($field->typeArgument);
    }

    /**
     * Test fromBaseSchema() applies type-specific nullable defaults.
     *
     * Text, date, datetime, json fields auto-nullable when not specified.
     */
    public function test_from_base_schema_auto_nullable_for_text_types(): void
    {
        // Arrange: text field without explicit nullable modifier
        $column = [
            'name' => 'notes',
            'type' => 'text',
            'modifiers' => [],
        ];

        // Act
        $field = FieldDefinition::fromBaseSchema($column);

        // Assert: text should be auto-nullable
        $this->assertTrue($field->nullable);
    }

    /**
     * Test fromBaseSchema() applies boolean default of 'true'.
     *
     * Boolean fields get default 'true' when no default specified.
     */
    public function test_from_base_schema_boolean_defaults_to_true(): void
    {
        // Arrange
        $column = [
            'name' => 'is_active',
            'type' => 'boolean',
        ];

        // Act
        $field = FieldDefinition::fromBaseSchema($column);

        // Assert: boolean should default to 'true'
        $this->assertSame('true', $field->default);
    }
}
