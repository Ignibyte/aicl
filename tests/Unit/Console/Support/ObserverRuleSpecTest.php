<?php

namespace Aicl\Tests\Unit\Console\Support;

use Aicl\Console\Support\ObserverRuleSpec;
use PHPUnit\Framework\TestCase;

class ObserverRuleSpecTest extends TestCase
{
    // ========================================================================
    // Value Object Construction
    // ========================================================================

    public function test_log_rule_construction(): void
    {
        $rule = new ObserverRuleSpec(
            event: 'created',
            action: 'log',
            details: 'Invoice "{model.name}" was created',
        );

        $this->assertSame('created', $rule->event);
        $this->assertSame('log', $rule->action);
        $this->assertSame('Invoice "{model.name}" was created', $rule->details);
        $this->assertNull($rule->watchField);
    }

    public function test_notify_rule_construction(): void
    {
        $rule = new ObserverRuleSpec(
            event: 'updated',
            action: 'notify',
            details: 'new owner: InvoiceAssigned',
            watchField: 'owner_id',
        );

        $this->assertSame('updated', $rule->event);
        $this->assertSame('notify', $rule->action);
        $this->assertSame('owner_id', $rule->watchField);
    }

    // ========================================================================
    // isLog() / isNotify()
    // ========================================================================

    public function test_is_log(): void
    {
        $rule = new ObserverRuleSpec(event: 'created', action: 'log', details: 'test');

        $this->assertTrue($rule->isLog());
        $this->assertFalse($rule->isNotify());
    }

    public function test_is_notify(): void
    {
        $rule = new ObserverRuleSpec(event: 'updated', action: 'notify', details: 'test');

        $this->assertFalse($rule->isLog());
        $this->assertTrue($rule->isNotify());
    }

    // ========================================================================
    // parseNotifyDetails()
    // ========================================================================

    public function test_parse_notify_simple(): void
    {
        $rule = new ObserverRuleSpec(
            event: 'updated',
            action: 'notify',
            details: 'owner: InvoiceStatusChanged',
        );

        $parsed = $rule->parseNotifyDetails();

        $this->assertSame('owner', $parsed['recipient']);
        $this->assertSame('InvoiceStatusChanged', $parsed['class']);
        $this->assertNull($parsed['condition']);
    }

    public function test_parse_notify_with_new_prefix(): void
    {
        $rule = new ObserverRuleSpec(
            event: 'updated',
            action: 'notify',
            details: 'new owner: InvoiceAssigned',
        );

        $parsed = $rule->parseNotifyDetails();

        $this->assertSame('owner', $parsed['recipient']);
        $this->assertSame('InvoiceAssigned', $parsed['class']);
    }

    public function test_parse_notify_with_condition(): void
    {
        $rule = new ObserverRuleSpec(
            event: 'created',
            action: 'notify',
            details: 'owner: InvoiceAssigned (if owner_id set)',
        );

        $parsed = $rule->parseNotifyDetails();

        $this->assertSame('owner', $parsed['recipient']);
        $this->assertSame('InvoiceAssigned', $parsed['class']);
        $this->assertSame('owner_id set', $parsed['condition']);
    }

    public function test_parse_notify_custom_recipient(): void
    {
        $rule = new ObserverRuleSpec(
            event: 'updated',
            action: 'notify',
            details: 'assigned_to: TaskAssigned',
            watchField: 'assigned_to_id',
        );

        $parsed = $rule->parseNotifyDetails();

        $this->assertSame('assigned_to', $parsed['recipient']);
        $this->assertSame('TaskAssigned', $parsed['class']);
    }

    // ========================================================================
    // Edge Cases
    // ========================================================================

    public function test_watch_field_null_for_non_update(): void
    {
        $rule = new ObserverRuleSpec(
            event: 'created',
            action: 'log',
            details: 'test',
        );

        $this->assertNull($rule->watchField);
    }

    public function test_parse_notify_no_class(): void
    {
        $rule = new ObserverRuleSpec(
            event: 'updated',
            action: 'notify',
            details: 'owner',
        );

        $parsed = $rule->parseNotifyDetails();

        $this->assertSame('owner', $parsed['recipient']);
        $this->assertSame('', $parsed['class']);
    }
}
