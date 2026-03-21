<?php

declare(strict_types=1);

namespace Aicl\Tests\Unit\Console\Support;

use Aicl\Console\Support\ObserverRuleSpec;
use PHPUnit\Framework\TestCase;

/**
 * Regression tests for ObserverRuleSpec PHPStan changes.
 *
 * Tests the preg_replace() ?? '' null coalescing additions in
 * parseNotifyDetails(). Under strict_types, preg_replace() can
 * return null on error, and the result needs explicit null handling.
 */
class ObserverRuleSpecRegressionTest extends TestCase
{
    /**
     * Test parseNotifyDetails extracts recipient and class from details.
     *
     * PHPStan change: Added ?? '' after preg_replace() calls for null safety.
     */
    public function test_parse_notify_details_extracts_recipient_and_class(): void
    {
        // Arrange: standard notify rule
        $spec = new ObserverRuleSpec(
            event: 'created',
            action: 'notify',
            details: 'owner: TaskCreatedNotification',
        );

        // Act
        $parsed = $spec->parseNotifyDetails();

        // Assert: should extract recipient and class
        $this->assertSame('owner', $parsed['recipient']);
        $this->assertSame('TaskCreatedNotification', $parsed['class']);
        $this->assertNull($parsed['condition']);
    }

    /**
     * Test parseNotifyDetails extracts condition from details.
     *
     * Verifies the "(if condition)" extraction works after
     * the preg_replace ?? '' change.
     */
    public function test_parse_notify_details_extracts_condition(): void
    {
        // Arrange: notify rule with condition
        $spec = new ObserverRuleSpec(
            event: 'updated',
            action: 'notify',
            details: 'new owner: AssignmentNotification (if owner_id set)',
            watchField: 'owner_id',
        );

        // Act
        $parsed = $spec->parseNotifyDetails();

        // Assert: condition should be extracted
        $this->assertSame('owner', $parsed['recipient']);
        $this->assertSame('AssignmentNotification', $parsed['class']);
        $this->assertSame('owner_id set', $parsed['condition']);
    }

    /**
     * Test parseNotifyDetails handles "new" prefix stripping.
     *
     * PHPStan change: preg_replace for "new " prefix uses ?? $recipient fallback.
     */
    public function test_parse_notify_details_strips_new_prefix(): void
    {
        // Arrange: recipient with "new " prefix
        $spec = new ObserverRuleSpec(
            event: 'updated',
            action: 'notify',
            details: 'new assignee: ReassignedNotification',
            watchField: 'assignee_id',
        );

        // Act
        $parsed = $spec->parseNotifyDetails();

        // Assert: "new " prefix should be stripped from recipient
        $this->assertSame('assignee', $parsed['recipient']);
        $this->assertSame('ReassignedNotification', $parsed['class']);
    }

    /**
     * Test parseNotifyDetails handles details without colon separator.
     *
     * Edge case: malformed details string.
     */
    public function test_parse_notify_details_handles_no_class(): void
    {
        // Arrange: details without class separator
        $spec = new ObserverRuleSpec(
            event: 'created',
            action: 'notify',
            details: 'admin',
        );

        // Act
        $parsed = $spec->parseNotifyDetails();

        // Assert: should not crash; class should be empty string
        $this->assertSame('admin', $parsed['recipient']);
        $this->assertSame('', $parsed['class']);
    }

    /**
     * Test isLog() returns correct value.
     */
    public function test_is_log_returns_true_for_log_action(): void
    {
        $spec = new ObserverRuleSpec('created', 'log', 'Entity was created');
        $this->assertTrue($spec->isLog());
        $this->assertFalse($spec->isNotify());
    }

    /**
     * Test isNotify() returns correct value.
     */
    public function test_is_notify_returns_true_for_notify_action(): void
    {
        $spec = new ObserverRuleSpec('created', 'notify', 'owner: CreatedNotification');
        $this->assertTrue($spec->isNotify());
        $this->assertFalse($spec->isLog());
    }
}
