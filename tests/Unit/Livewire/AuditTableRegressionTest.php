<?php

declare(strict_types=1);

namespace Aicl\Tests\Unit\Livewire;

use Aicl\Livewire\AuditTable;
use PHPUnit\Framework\TestCase;

/**
 * Regression tests for AuditTable Livewire component PHPStan changes.
 *
 * Covers the declare(strict_types=1) enforcement and the
 * created_at?->format() null guard with ?? '' fallback in the
 * tooltip callback on the created_at column.
 */
class AuditTableRegressionTest extends TestCase
{
    // -- Class structure --

    /**
     * Test AuditTable has declare(strict_types=1).
     *
     * PHPStan change: Added strict types declaration.
     */
    public function test_class_has_strict_types(): void
    {
        // Arrange
        $reflection = new \ReflectionClass(AuditTable::class);
        $filename = $reflection->getFileName();
        $this->assertNotFalse($filename);
        $contents = file_get_contents($filename);
        $this->assertNotFalse($contents);

        // Assert
        $this->assertStringContainsString('declare(strict_types=1)', $contents);
    }

    /**
     * Test AuditTable source contains null-safe created_at access.
     *
     * PHPStan change: $record->created_at?->format() with ?? '' fallback.
     */
    public function test_source_has_null_safe_created_at(): void
    {
        // Arrange
        $reflection = new \ReflectionClass(AuditTable::class);
        $filename = $reflection->getFileName();
        $this->assertNotFalse($filename);
        $contents = file_get_contents($filename);
        $this->assertNotFalse($contents);

        // Assert: contains the null-safe pattern
        $this->assertStringContainsString('created_at?->format(', $contents);
    }

    /**
     * Test AuditTable is not auto-discovered as a Filament widget.
     *
     * The $isDiscovered = false property ensures manual registration only.
     */
    public function test_is_not_auto_discovered(): void
    {
        // Arrange
        $reflection = new \ReflectionClass(AuditTable::class);
        $property = $reflection->getProperty('isDiscovered');

        // Assert
        $this->assertFalse($property->getDefaultValue());
    }
}
