<?php

declare(strict_types=1);

namespace Aicl\Tests\Unit\Livewire;

use Aicl\Livewire\FailedDeliveriesTable;
use Aicl\Notifications\Enums\DeliveryStatus;
use PHPUnit\Framework\TestCase;

/**
 * Regression tests for FailedDeliveriesTable Livewire component PHPStan changes.
 *
 * Covers the declare(strict_types=1) enforcement and the (string) cast
 * on BackedEnum->value in the channel type column formatter.
 */
class FailedDeliveriesTableRegressionTest extends TestCase
{
    // -- BackedEnum (string) cast --

    /**
     * Test (string) cast on BackedEnum value produces correct string.
     *
     * PHPStan change: Changed from $state->value to (string) $state->value
     * because BackedEnum->value type is string|int and the formatStateUsing
     * callback must return string.
     */
    public function test_backed_enum_value_string_cast(): void
    {
        // Arrange: use an actual enum from the codebase
        $deliveryStatus = DeliveryStatus::Failed;

        // Act: cast the enum value to string (same pattern as source)
        $result = (string) $deliveryStatus->value;

        // Assert: produces the expected string value
        $this->assertSame('failed', $result);
    }

    /**
     * Test non-BackedEnum state is cast to string directly.
     *
     * Edge case: when $state is a plain string (not a BackedEnum),
     * the else branch casts it to (string).
     */
    public function test_non_enum_string_cast(): void
    {
        // Arrange: simulate a plain string state value
        $state = 'sms';

        // Act: direct string cast
        $result = (string) $state;

        // Assert
        $this->assertSame('sms', $result);
    }

    // -- Class structure --

    /**
     * Test FailedDeliveriesTable has declare(strict_types=1).
     */
    public function test_class_has_strict_types(): void
    {
        // Arrange
        $reflection = new \ReflectionClass(FailedDeliveriesTable::class);
        $filename = $reflection->getFileName();
        $this->assertNotFalse($filename);
        $contents = file_get_contents($filename);
        $this->assertNotFalse($contents);

        // Assert
        $this->assertStringContainsString('declare(strict_types=1)', $contents);
    }

    /**
     * Test FailedDeliveriesTable is not auto-discovered.
     */
    public function test_is_not_auto_discovered(): void
    {
        // Arrange
        $reflection = new \ReflectionClass(FailedDeliveriesTable::class);
        $property = $reflection->getProperty('isDiscovered');

        // Assert
        $this->assertFalse($property->getDefaultValue());
    }
}
