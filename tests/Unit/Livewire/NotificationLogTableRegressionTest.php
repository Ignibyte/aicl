<?php

declare(strict_types=1);

namespace Aicl\Tests\Unit\Livewire;

use Aicl\Livewire\NotificationLogTable;
use Filament\Widgets\TableWidget;
use PHPUnit\Framework\TestCase;

/**
 * Regression tests for NotificationLogTable Livewire component PHPStan changes.
 *
 * Covers the declare(strict_types=1) enforcement and the
 * created_at?->format() null guard with ?? '' fallback in the
 * tooltip callback on the created_at column.
 */
class NotificationLogTableRegressionTest extends TestCase
{
    // -- Class structure --

    /**
     * Test NotificationLogTable has declare(strict_types=1).
     */
    public function test_class_has_strict_types(): void
    {
        // Arrange
        $reflection = new \ReflectionClass(NotificationLogTable::class);
        $filename = $reflection->getFileName();
        $this->assertNotFalse($filename);
        $contents = file_get_contents($filename);
        $this->assertNotFalse($contents);

        // Assert
        $this->assertStringContainsString('declare(strict_types=1)', $contents);
    }

    /**
     * Test source contains null-safe created_at access.
     *
     * PHPStan change: $record->created_at?->format() with ?? '' fallback.
     */
    public function test_source_has_null_safe_created_at(): void
    {
        // Arrange
        $reflection = new \ReflectionClass(NotificationLogTable::class);
        $filename = $reflection->getFileName();
        $this->assertNotFalse($filename);
        $contents = file_get_contents($filename);
        $this->assertNotFalse($contents);

        // Assert
        $this->assertStringContainsString('created_at?->format(', $contents);
    }

    /**
     * Test NotificationLogTable is not auto-discovered.
     */
    public function test_is_not_auto_discovered(): void
    {
        // Arrange
        $reflection = new \ReflectionClass(NotificationLogTable::class);
        $property = $reflection->getProperty('isDiscovered');

        // Assert
        $this->assertFalse($property->getDefaultValue());
    }

    /**
     * Test class extends Filament TableWidget.
     */
    public function test_extends_table_widget(): void
    {
        // Assert: verify parent class via reflection
        $reflection = new \ReflectionClass(NotificationLogTable::class);
        $parentClass = $reflection->getParentClass();
        $this->assertNotFalse($parentClass);
        $this->assertSame(TableWidget::class, $parentClass->getName());
    }
}
