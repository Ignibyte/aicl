<?php

namespace Aicl\Tests\Unit\Console\Support;

use Aicl\Console\Support\NotificationSpec;
use PHPUnit\Framework\TestCase;

class NotificationSpecTest extends TestCase
{
    // ========================================================================
    // Value Object Construction
    // ========================================================================

    public function test_notification_spec_construction(): void
    {
        $spec = new NotificationSpec(
            name: 'Assigned',
            trigger: 'field_change: assigned_to',
            title: 'Invoice assigned to you',
            body: '"{model.name}" was assigned to you by {actor.name}.',
            icon: 'heroicon-o-user-plus',
            color: 'primary',
            recipient: 'assigned_to',
            channels: ['database'],
        );

        $this->assertSame('Assigned', $spec->name);
        $this->assertSame('field_change: assigned_to', $spec->trigger);
        $this->assertSame('Invoice assigned to you', $spec->title);
        $this->assertSame('heroicon-o-user-plus', $spec->icon);
        $this->assertSame('primary', $spec->color);
        $this->assertSame('assigned_to', $spec->recipient);
        $this->assertSame(['database'], $spec->channels);
    }

    public function test_notification_spec_defaults(): void
    {
        $spec = new NotificationSpec(
            name: 'Test',
            trigger: 'created',
            title: 'Test',
            body: 'Test body',
        );

        $this->assertSame('heroicon-o-bell', $spec->icon);
        $this->assertSame('primary', $spec->color);
        $this->assertSame('owner', $spec->recipient);
        $this->assertSame(['database'], $spec->channels);
    }

    // ========================================================================
    // Trigger Type
    // ========================================================================

    public function test_trigger_type_field_change(): void
    {
        $spec = new NotificationSpec(
            name: 'Assigned',
            trigger: 'field_change: assigned_to',
            title: 'Test',
            body: 'Test',
        );

        $this->assertSame('field_change', $spec->triggerType());
    }

    public function test_trigger_type_state_transition(): void
    {
        $spec = new NotificationSpec(
            name: 'StatusChanged',
            trigger: 'state_transition: draft → active',
            title: 'Test',
            body: 'Test',
        );

        $this->assertSame('state_transition', $spec->triggerType());
    }

    public function test_trigger_type_created(): void
    {
        $spec = new NotificationSpec(
            name: 'Created',
            trigger: 'created',
            title: 'Test',
            body: 'Test',
        );

        $this->assertSame('created', $spec->triggerType());
    }

    public function test_trigger_type_deleted(): void
    {
        $spec = new NotificationSpec(
            name: 'Deleted',
            trigger: 'deleted',
            title: 'Test',
            body: 'Test',
        );

        $this->assertSame('deleted', $spec->triggerType());
    }

    // ========================================================================
    // Watched Field
    // ========================================================================

    public function test_watched_field_for_field_change(): void
    {
        $spec = new NotificationSpec(
            name: 'Assigned',
            trigger: 'field_change: assigned_to',
            title: 'Test',
            body: 'Test',
        );

        $this->assertSame('assigned_to', $spec->watchedField());
    }

    public function test_watched_field_for_status(): void
    {
        $spec = new NotificationSpec(
            name: 'StatusChanged',
            trigger: 'field_change: status',
            title: 'Test',
            body: 'Test',
        );

        $this->assertSame('status', $spec->watchedField());
    }

    public function test_watched_field_null_for_non_field_change(): void
    {
        $spec = new NotificationSpec(
            name: 'Created',
            trigger: 'created',
            title: 'Test',
            body: 'Test',
        );

        $this->assertNull($spec->watchedField());
    }

    // ========================================================================
    // Dynamic Color
    // ========================================================================

    public function test_has_dynamic_color_true(): void
    {
        $spec = new NotificationSpec(
            name: 'StatusChanged',
            trigger: 'field_change: status',
            title: 'Test',
            body: 'Test',
            color: 'new.status.color',
        );

        $this->assertTrue($spec->hasDynamicColor());
    }

    public function test_has_dynamic_color_false(): void
    {
        $spec = new NotificationSpec(
            name: 'Assigned',
            trigger: 'field_change: assigned_to',
            title: 'Test',
            body: 'Test',
            color: 'primary',
        );

        $this->assertFalse($spec->hasDynamicColor());
    }

    // ========================================================================
    // Multiple Channels
    // ========================================================================

    public function test_multiple_channels(): void
    {
        $spec = new NotificationSpec(
            name: 'StatusChanged',
            trigger: 'field_change: status',
            title: 'Test',
            body: 'Test',
            channels: ['database', 'mail', 'broadcast'],
        );

        $this->assertCount(3, $spec->channels);
        $this->assertContains('database', $spec->channels);
        $this->assertContains('mail', $spec->channels);
        $this->assertContains('broadcast', $spec->channels);
    }
}
