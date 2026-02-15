<?php

namespace Aicl\Tests\Unit\Console\Support;

use Aicl\Console\Support\NotificationTemplateResolver;
use PHPUnit\Framework\TestCase;

class NotificationTemplateResolverTest extends TestCase
{
    // ========================================================================
    // resolveBody()
    // ========================================================================

    public function test_resolve_model_field(): void
    {
        $resolver = new NotificationTemplateResolver('Invoice');

        $result = $resolver->resolveBody('"{model.name}" was updated.');

        $this->assertSame('"{$this->invoice->name}" was updated.', $result);
    }

    public function test_resolve_multiple_model_fields(): void
    {
        $resolver = new NotificationTemplateResolver('Invoice');

        $result = $resolver->resolveBody('{model.name} (#{model.number}) was assigned.');

        $this->assertSame('{$this->invoice->name} (#{$this->invoice->number}) was assigned.', $result);
    }

    public function test_resolve_actor_name(): void
    {
        $resolver = new NotificationTemplateResolver('Invoice');

        $result = $resolver->resolveBody('Assigned by {actor.name}.');

        $this->assertSame('Assigned by {\$this->changedBy->name}.', $result);
    }

    public function test_resolve_old_status_label(): void
    {
        $resolver = new NotificationTemplateResolver('Invoice');

        $result = $resolver->resolveBody('Changed from {old.status.label} to {new.status.label}.');

        $this->assertSame('Changed from {\$this->previousStatus->label()} to {\$this->newStatus->label()}.', $result);
    }

    public function test_resolve_generic_old_field_label(): void
    {
        $resolver = new NotificationTemplateResolver('Invoice');

        $result = $resolver->resolveBody('Priority changed from {old.priority.label}.');

        $this->assertSame('Priority changed from {\$this->previousPriority->label()}.', $result);
    }

    public function test_resolve_generic_new_field_label(): void
    {
        $resolver = new NotificationTemplateResolver('Invoice');

        $result = $resolver->resolveBody('Priority is now {new.priority.label}.');

        $this->assertSame('Priority is now {\$this->newPriority->label()}.', $result);
    }

    public function test_resolve_combined_template(): void
    {
        $resolver = new NotificationTemplateResolver('Task');

        $result = $resolver->resolveBody('"{model.title}" was changed from {old.status.label} to {new.status.label} by {actor.name}.');

        $this->assertSame('"{$this->task->title}" was changed from {\$this->previousStatus->label()} to {\$this->newStatus->label()} by {\$this->changedBy->name}.', $result);
    }

    public function test_resolve_snake_case_entity_name(): void
    {
        $resolver = new NotificationTemplateResolver('PurchaseOrder');

        $result = $resolver->resolveBody('"{model.name}" was created.');

        $this->assertSame('"{$this->purchase_order->name}" was created.', $result);
    }

    // ========================================================================
    // resolveColor()
    // ========================================================================

    public function test_resolve_static_color(): void
    {
        $resolver = new NotificationTemplateResolver('Invoice');

        $result = $resolver->resolveColor('primary');

        $this->assertSame("'primary'", $result);
    }

    public function test_resolve_dynamic_status_color(): void
    {
        $resolver = new NotificationTemplateResolver('Invoice');

        $result = $resolver->resolveColor('new.status.color');

        $this->assertSame('$this->newStatus->color()', $result);
    }

    public function test_resolve_dynamic_priority_color(): void
    {
        $resolver = new NotificationTemplateResolver('Invoice');

        $result = $resolver->resolveColor('new.priority.color');

        $this->assertSame('$this->newPriority->color()', $result);
    }

    // ========================================================================
    // resolveRecipient()
    // ========================================================================

    public function test_resolve_owner_recipient(): void
    {
        $resolver = new NotificationTemplateResolver('Invoice');

        $result = $resolver->resolveRecipient('owner');

        $this->assertSame('$invoice->owner', $result);
    }

    public function test_resolve_assigned_to_recipient(): void
    {
        $resolver = new NotificationTemplateResolver('Invoice');

        $result = $resolver->resolveRecipient('assigned_to');

        $this->assertSame('$invoice->assignedTo', $result);
    }

    public function test_resolve_assigned_to_id_recipient(): void
    {
        $resolver = new NotificationTemplateResolver('Invoice');

        $result = $resolver->resolveRecipient('assigned_to_id');

        $this->assertSame('$invoice->assignedTo', $result);
    }

    public function test_resolve_custom_field_recipient(): void
    {
        $resolver = new NotificationTemplateResolver('Task');

        $result = $resolver->resolveRecipient('reviewer');

        $this->assertSame('$task->reviewer', $result);
    }
}
