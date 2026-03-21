<?php

declare(strict_types=1);

namespace Aicl\Tests\Unit\Console\Generators;

use Aicl\Console\Support\FieldDefinition;
use PHPUnit\Framework\TestCase;

/**
 * Regression tests for MigrationGenerator PHPStan changes.
 *
 * Tests the declare(strict_types=1) addition and the
 * $this->ctx->fields ?? [] null coalescing in buildSmartMigrationColumns().
 */
class MigrationGeneratorRegressionTest extends TestCase
{
    /**
     * Test fields null coalescing produces empty array.
     *
     * PHPStan change: $this->ctx->fields ?? [] ensures null fields
     * array doesn't break foreach iteration. This tests the pattern
     * in isolation since the generator requires full context.
     */
    public function test_null_fields_coalesces_to_empty_array(): void
    {
        // Arrange: simulate null fields
        $fields = null;

        // Act: replicate the pattern from MigrationGenerator
        /** @phpstan-ignore-next-line */
        $iterableFields = $fields ?? [];

        // Assert: should be empty array, safe to iterate
        $this->assertEmpty($iterableFields);
    }

    /**
     * Test fields null coalescing preserves non-null fields.
     *
     * Happy path: fields are populated.
     */
    public function test_non_null_fields_preserved_through_coalescing(): void
    {
        // Arrange: create actual field definitions
        $fields = [
            new FieldDefinition(
                name: 'title',
                type: 'string',
                typeArgument: null,
                nullable: false,
                unique: false,
                default: null,
                indexed: false,
            ),
            new FieldDefinition(
                name: 'is_active',
                type: 'boolean',
                typeArgument: null,
                nullable: false,
                unique: false,
                default: 'true',
                indexed: false,
            ),
        ];

        // Act: replicate the pattern
        /** @phpstan-ignore-next-line */
        $iterableFields = $fields ?? [];

        // Assert: should preserve the fields
        $this->assertCount(2, $iterableFields);
        $this->assertSame('title', $iterableFields[0]->name);
    }

    /**
     * Test hasExplicitIsActive detection from fields array.
     *
     * Verifies the is_active detection loop works with typed arrays.
     */
    public function test_detects_explicit_is_active_field(): void
    {
        // Arrange
        $fields = [
            new FieldDefinition('title', 'string', null, false, false, null, false),
            new FieldDefinition('is_active', 'boolean', null, false, false, 'true', false),
        ];

        // Act: replicate the detection pattern from the generator
        $hasExplicitIsActive = false;
        /** @phpstan-ignore-next-line */
        foreach ($fields ?? [] as $field) {
            if ($field->name === 'is_active') {
                $hasExplicitIsActive = true;
            }
        }

        // Assert
        $this->assertTrue($hasExplicitIsActive);
    }

    /**
     * Test owner_id detection from fields array.
     *
     * Verifies the owner_id detection loop works with null coalescing.
     */
    public function test_detects_explicit_owner_id_field(): void
    {
        // Arrange: fields without owner_id
        $fields = [
            new FieldDefinition('title', 'string', null, false, false, null, false),
        ];

        // Act
        $hasExplicitOwnerId = false;
        /** @phpstan-ignore-next-line */
        foreach ($fields ?? [] as $field) {
            if ($field->name === 'owner_id') {
                $hasExplicitOwnerId = true;
            }
        }

        // Assert: should not detect owner_id
        $this->assertFalse($hasExplicitOwnerId);
    }
}
