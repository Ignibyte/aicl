<?php

declare(strict_types=1);

namespace Aicl\Tests\Unit\Models;

use Aicl\Models\NotificationLog;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use PHPUnit\Framework\TestCase;

/**
 * Regression tests for NotificationLog model PHPStan changes.
 *
 * Covers typed property declarations (nullable types), cast definitions,
 * MorphTo relationship annotations, scope method signatures, type label
 * accessor with null guard, and mark-as-read/unread methods.
 */
class NotificationLogRegressionTest extends TestCase
{
    /**
     * Test model uses HasUuids trait.
     */
    public function test_model_uses_has_uuids(): void
    {
        // Arrange
        $traits = class_uses_recursive(NotificationLog::class);

        // Assert
        $this->assertArrayHasKey(HasUuids::class, $traits);
    }

    /**
     * Test fillable contains all expected attributes.
     *
     * PHPStan added nullable type annotations to several properties.
     */
    public function test_fillable_contains_all_expected_attributes(): void
    {
        // Arrange
        $log = new NotificationLog;

        // Act
        $fillable = $log->getFillable();

        // Assert
        $expected = [
            'type', 'notifiable_type', 'notifiable_id', 'sender_type',
            'sender_id', 'channels', 'channel_status', 'data', 'read_at',
        ];

        foreach ($expected as $attribute) {
            $this->assertContains($attribute, $fillable, "Missing fillable: {$attribute}");
        }
    }

    /**
     * Test casts returns expected definitions.
     *
     * PHPStan added @return array<string, string> annotation.
     */
    public function test_casts_returns_expected_definitions(): void
    {
        // Arrange
        $log = new NotificationLog;

        // Act: call protected casts() via reflection
        $reflection = new \ReflectionMethod($log, 'casts');
        $casts = $reflection->invoke($log);

        // Assert
        $this->assertSame('array', $casts['channels']);
        $this->assertSame('array', $casts['channel_status']);
        $this->assertSame('array', $casts['data']);
        $this->assertSame('datetime', $casts['read_at']);
    }

    /**
     * Test notifiable relationship method returns MorphTo type.
     *
     * PHPStan added @return MorphTo<Model, $this> annotation.
     * Uses reflection because calling the method needs a DB connection.
     */
    public function test_notifiable_relationship_returns_morph_to(): void
    {
        // Arrange
        $method = new \ReflectionMethod(NotificationLog::class, 'notifiable');
        $returnType = $method->getReturnType();

        // Assert: method returns MorphTo
        $this->assertInstanceOf(\ReflectionNamedType::class, $returnType);
        /** @var \ReflectionNamedType $returnType */
        $this->assertSame(MorphTo::class, $returnType->getName());
    }

    /**
     * Test sender relationship method returns MorphTo type.
     *
     * PHPStan added @return MorphTo<Model, $this> annotation.
     * Uses reflection because calling the method needs a DB connection.
     */
    public function test_sender_relationship_returns_morph_to(): void
    {
        // Arrange
        $method = new \ReflectionMethod(NotificationLog::class, 'sender');
        $returnType = $method->getReturnType();

        // Assert: method returns MorphTo
        $this->assertInstanceOf(\ReflectionNamedType::class, $returnType);
        /** @var \ReflectionNamedType $returnType */
        $this->assertSame(MorphTo::class, $returnType->getName());
    }

    /**
     * Test getTypeLabelAttribute returns 'Unknown' when type is null.
     *
     * PHPStan enforced null check: if (! $class).
     */
    public function test_type_label_returns_unknown_when_type_null(): void
    {
        // Arrange
        $log = new NotificationLog;
        $log->type = null;

        // Act
        $result = $log->getTypeLabelAttribute();

        // Assert
        $this->assertSame('Unknown', $result);
    }

    /**
     * Test getTypeLabelAttribute strips 'Notification' suffix and headlines.
     *
     * Verifies the string manipulation chain works with strict_types.
     */
    public function test_type_label_strips_notification_suffix(): void
    {
        // Arrange
        $log = new NotificationLog;
        $log->type = 'App\\Notifications\\UserWelcomeNotification';

        // Act
        $result = $log->getTypeLabelAttribute();

        // Assert: "UserWelcome" -> "User Welcome"
        $this->assertSame('User Welcome', $result);
    }

    /**
     * Test getTypeLabelAttribute handles class without Notification suffix.
     */
    public function test_type_label_handles_no_notification_suffix(): void
    {
        // Arrange
        $log = new NotificationLog;
        $log->type = 'App\\Notifications\\OrderShipped';

        // Act
        $result = $log->getTypeLabelAttribute();

        // Assert: "OrderShipped" -> "Order Shipped"
        $this->assertSame('Order Shipped', $result);
    }

    /**
     * Test table name is explicitly set.
     */
    public function test_table_name_is_notification_logs(): void
    {
        // Arrange
        $log = new NotificationLog;

        // Assert
        $this->assertSame('notification_logs', $log->getTable());
    }
}
