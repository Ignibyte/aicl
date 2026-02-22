<?php

namespace Aicl\Tests\Unit\Console\Support;

use Aicl\Console\Support\SpecValidation;
use PHPUnit\Framework\TestCase;

class SpecValidationTest extends TestCase
{
    use SpecValidation;

    // ========================================================================
    // isPascalCase()
    // ========================================================================

    public function test_pascal_case_valid(): void
    {
        $this->assertTrue($this->isPascalCase('Invoice'));
        $this->assertTrue($this->isPascalCase('InvoiceLineItem'));
        $this->assertTrue($this->isPascalCase('Task123'));
    }

    public function test_pascal_case_invalid(): void
    {
        $this->assertFalse($this->isPascalCase('invoice'));
        $this->assertFalse($this->isPascalCase('invoice_item'));
        $this->assertFalse($this->isPascalCase('123Task'));
        $this->assertFalse($this->isPascalCase(''));
        $this->assertFalse($this->isPascalCase('A')); // Must be 2+ chars
    }

    // ========================================================================
    // isSnakeCase()
    // ========================================================================

    public function test_snake_case_valid(): void
    {
        $this->assertTrue($this->isSnakeCase('invoice'));
        $this->assertTrue($this->isSnakeCase('invoice_line_item'));
        $this->assertTrue($this->isSnakeCase('task123'));
        $this->assertTrue($this->isSnakeCase('a'));
    }

    public function test_snake_case_invalid(): void
    {
        $this->assertFalse($this->isSnakeCase('Invoice'));
        $this->assertFalse($this->isSnakeCase('invoiceItem'));
        $this->assertFalse($this->isSnakeCase('123task'));
        $this->assertFalse($this->isSnakeCase(''));
        $this->assertFalse($this->isSnakeCase('invoice-item'));
    }

    // ========================================================================
    // isCamelCase()
    // ========================================================================

    public function test_camel_case_valid(): void
    {
        $this->assertTrue($this->isCamelCase('lineItems'));
        $this->assertTrue($this->isCamelCase('tags'));
        $this->assertTrue($this->isCamelCase('owner'));
        $this->assertTrue($this->isCamelCase('invoiceLineItem'));
    }

    public function test_camel_case_invalid(): void
    {
        $this->assertFalse($this->isCamelCase('LineItems'));
        $this->assertFalse($this->isCamelCase('line_items'));
        $this->assertFalse($this->isCamelCase('123items'));
        $this->assertFalse($this->isCamelCase(''));
    }

    // ========================================================================
    // isValidColumnType()
    // ========================================================================

    public function test_valid_column_types(): void
    {
        $validTypes = ['string', 'text', 'integer', 'float', 'boolean', 'date', 'datetime', 'enum', 'json', 'foreignId'];

        foreach ($validTypes as $type) {
            $this->assertTrue($this->isValidColumnType($type), "Type '{$type}' should be valid.");
        }
    }

    public function test_invalid_column_types(): void
    {
        $this->assertFalse($this->isValidColumnType('uuid'));
        $this->assertFalse($this->isValidColumnType('timestamp'));
        $this->assertFalse($this->isValidColumnType('binary'));
        $this->assertFalse($this->isValidColumnType(''));
    }

    // ========================================================================
    // isReservedColumn()
    // ========================================================================

    public function test_reserved_columns(): void
    {
        $this->assertTrue($this->isReservedColumn('id'));
        $this->assertTrue($this->isReservedColumn('created_at'));
        $this->assertTrue($this->isReservedColumn('updated_at'));
        $this->assertTrue($this->isReservedColumn('deleted_at'));
    }

    public function test_non_reserved_columns(): void
    {
        $this->assertFalse($this->isReservedColumn('title'));
        $this->assertFalse($this->isReservedColumn('name'));
        $this->assertFalse($this->isReservedColumn('is_active'));
    }

    // ========================================================================
    // isAutoColumn()
    // ========================================================================

    public function test_auto_columns(): void
    {
        $this->assertTrue($this->isAutoColumn('is_active'));
        $this->assertTrue($this->isAutoColumn('owner_id'));
    }

    public function test_non_auto_columns(): void
    {
        $this->assertFalse($this->isAutoColumn('id'));
        $this->assertFalse($this->isAutoColumn('title'));
        $this->assertFalse($this->isAutoColumn('status'));
    }

    // ========================================================================
    // isKnownTrait()
    // ========================================================================

    public function test_known_traits(): void
    {
        $this->assertTrue($this->isKnownTrait('HasEntityEvents'));
        $this->assertTrue($this->isKnownTrait('HasAuditTrail'));
        $this->assertTrue($this->isKnownTrait('HasStandardScopes'));
        $this->assertTrue($this->isKnownTrait('HasTagging'));
        $this->assertTrue($this->isKnownTrait('HasSearchableFields'));
        $this->assertTrue($this->isKnownTrait('HasAiContext'));
    }

    public function test_unknown_traits(): void
    {
        $this->assertFalse($this->isKnownTrait('HasCustomThing'));
        $this->assertFalse($this->isKnownTrait('SoftDeletes'));
        $this->assertFalse($this->isKnownTrait(''));
    }

    // ========================================================================
    // isKnownOption()
    // ========================================================================

    public function test_known_options(): void
    {
        $this->assertTrue($this->isKnownOption('widgets'));
        $this->assertTrue($this->isKnownOption('notifications'));
        $this->assertTrue($this->isKnownOption('pdf'));
        $this->assertTrue($this->isKnownOption('ai-context'));
        $this->assertTrue($this->isKnownOption('filament'));
        $this->assertTrue($this->isKnownOption('api'));
        $this->assertTrue($this->isKnownOption('base'));
    }

    public function test_unknown_options(): void
    {
        $this->assertFalse($this->isKnownOption('custom'));
        $this->assertFalse($this->isKnownOption('debug'));
        $this->assertFalse($this->isKnownOption(''));
    }

    // ========================================================================
    // isValidRelationshipType()
    // ========================================================================

    public function test_valid_relationship_types(): void
    {
        $this->assertTrue($this->isValidRelationshipType('hasMany'));
        $this->assertTrue($this->isValidRelationshipType('hasOne'));
        $this->assertTrue($this->isValidRelationshipType('belongsToMany'));
        $this->assertTrue($this->isValidRelationshipType('morphMany'));
    }

    public function test_invalid_relationship_types(): void
    {
        $this->assertFalse($this->isValidRelationshipType('belongsTo'));
        $this->assertFalse($this->isValidRelationshipType('morphTo'));
        $this->assertFalse($this->isValidRelationshipType('hasManyThrough'));
        $this->assertFalse($this->isValidRelationshipType(''));
    }
}
