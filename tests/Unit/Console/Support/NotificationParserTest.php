<?php

namespace Aicl\Tests\Unit\Console\Support;

use Aicl\Console\Support\SpecFileParser;
use PHPUnit\Framework\TestCase;

class NotificationParserTest extends TestCase
{
    protected SpecFileParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new SpecFileParser;
    }

    // ========================================================================
    // Structured ## Notifications parsing
    // ========================================================================

    public function test_parse_structured_notification_assigned(): void
    {
        $content = <<<'MD'
# Invoice

An invoice entity.

## Fields

| Name | Type | Modifiers |
|------|------|-----------|
| title | string | |
| assigned_to | foreignId:users | |

## Notifications

### Assigned

| Field | Value |
|-------|-------|
| Trigger | field_change: assigned_to |
| Title | Invoice assigned to you |
| Body | "{model.name}" was assigned to you by {actor.name}. |
| Icon | heroicon-o-user-plus |
| Color | primary |
| Recipient | assigned_to |
| Channels | database |

## Options

- notifications: true
MD;

        $spec = $this->parser->parseContent($content);

        $this->assertNotNull($spec->notificationSpecs);
        $this->assertCount(1, $spec->notificationSpecs);
        $this->assertSame('Assigned', $spec->notificationSpecs[0]->name);
        $this->assertSame('field_change: assigned_to', $spec->notificationSpecs[0]->trigger);
        $this->assertSame('Invoice assigned to you', $spec->notificationSpecs[0]->title);
        $this->assertStringContainsString('{model.name}', $spec->notificationSpecs[0]->body);
        $this->assertSame('heroicon-o-user-plus', $spec->notificationSpecs[0]->icon);
        $this->assertSame('primary', $spec->notificationSpecs[0]->color);
        $this->assertSame('assigned_to', $spec->notificationSpecs[0]->recipient);
        $this->assertSame(['database'], $spec->notificationSpecs[0]->channels);
    }

    public function test_parse_structured_notification_status_changed(): void
    {
        $content = <<<'MD'
# Invoice

An invoice entity.

## Fields

| Name | Type | Modifiers |
|------|------|-----------|
| title | string | |

## States

- draft
- active
- paid

## Notifications

### StatusChanged

| Field | Value |
|-------|-------|
| Trigger | field_change: status |
| Title | Invoice status changed |
| Body | The status of "{model.name}" was changed from {old.status.label} to {new.status.label} by {actor.name}. |
| Icon | heroicon-o-arrow-path |
| Color | new.status.color |
| Recipient | owner |
| Channels | database, mail |
MD;

        $spec = $this->parser->parseContent($content);

        $this->assertNotNull($spec->notificationSpecs);
        $this->assertCount(1, $spec->notificationSpecs);
        $this->assertSame('StatusChanged', $spec->notificationSpecs[0]->name);
        $this->assertSame('field_change: status', $spec->notificationSpecs[0]->trigger);
        $this->assertSame('new.status.color', $spec->notificationSpecs[0]->color);
        $this->assertSame(['database', 'mail'], $spec->notificationSpecs[0]->channels);
        $this->assertTrue($spec->notificationSpecs[0]->hasDynamicColor());
        $this->assertSame('status', $spec->notificationSpecs[0]->watchedField());
    }

    public function test_parse_multiple_notifications(): void
    {
        $content = <<<'MD'
# Invoice

An invoice entity.

## Fields

| Name | Type | Modifiers |
|------|------|-----------|
| title | string | |
| assigned_to | foreignId:users | |

## Notifications

### Assigned

| Field | Value |
|-------|-------|
| Trigger | field_change: assigned_to |
| Title | Invoice assigned to you |
| Body | "{model.name}" was assigned to you by {actor.name}. |
| Icon | heroicon-o-user-plus |
| Color | primary |
| Recipient | assigned_to |
| Channels | database |

### StatusChanged

| Field | Value |
|-------|-------|
| Trigger | field_change: status |
| Title | Invoice status changed |
| Body | The status of "{model.name}" was changed. |
| Icon | heroicon-o-arrow-path |
| Color | info |
| Recipient | owner |
| Channels | database, mail |
MD;

        $spec = $this->parser->parseContent($content);

        $this->assertNotNull($spec->notificationSpecs);
        $this->assertCount(2, $spec->notificationSpecs);
        $this->assertSame('Assigned', $spec->notificationSpecs[0]->name);
        $this->assertSame('StatusChanged', $spec->notificationSpecs[1]->name);
    }

    public function test_parse_created_trigger(): void
    {
        $content = <<<'MD'
# Task

A task entity.

## Fields

| Name | Type | Modifiers |
|------|------|-----------|
| title | string | |

## Notifications

### Created

| Field | Value |
|-------|-------|
| Trigger | created |
| Title | New task created |
| Body | A new task "{model.title}" has been created. |
| Icon | heroicon-o-plus-circle |
| Color | success |
| Recipient | owner |
| Channels | database |
MD;

        $spec = $this->parser->parseContent($content);

        $this->assertNotNull($spec->notificationSpecs);
        $this->assertCount(1, $spec->notificationSpecs);
        $this->assertSame('created', $spec->notificationSpecs[0]->triggerType());
        $this->assertNull($spec->notificationSpecs[0]->watchedField());
    }

    public function test_parse_notification_with_defaults(): void
    {
        $content = <<<'MD'
# Task

A task entity.

## Fields

| Name | Type | Modifiers |
|------|------|-----------|
| title | string | |

## Notifications

### Updated

| Field | Value |
|-------|-------|
| Trigger | field_change: title |
| Title | Task title changed |
| Body | The task was renamed. |
MD;

        $spec = $this->parser->parseContent($content);

        $this->assertNotNull($spec->notificationSpecs);
        $this->assertSame('heroicon-o-bell', $spec->notificationSpecs[0]->icon);
        $this->assertSame('primary', $spec->notificationSpecs[0]->color);
        $this->assertSame('owner', $spec->notificationSpecs[0]->recipient);
        $this->assertSame(['database'], $spec->notificationSpecs[0]->channels);
    }

    // ========================================================================
    // Backward compatibility — legacy Notification Hints still work
    // ========================================================================

    public function test_legacy_notification_hints_no_structured_notifications(): void
    {
        $content = <<<'MD'
# Invoice

An invoice entity.

## Fields

| Name | Type | Modifiers |
|------|------|-----------|
| title | string | |

## Notification Hints

- InvoiceAssigned: When assigned_to changes
- InvoiceStatusChanged: On any state transition
MD;

        $spec = $this->parser->parseContent($content);

        $this->assertNull($spec->notificationSpecs);
        $this->assertCount(2, $spec->notificationHints);
        $this->assertStringContainsString('InvoiceAssigned', $spec->notificationHints[0]);
        $this->assertStringContainsString('InvoiceStatusChanged', $spec->notificationHints[1]);
    }

    public function test_no_notifications_section_returns_null(): void
    {
        $content = <<<'MD'
# Simple

A simple entity.

## Fields

| Name | Type | Modifiers |
|------|------|-----------|
| title | string | |
MD;

        $spec = $this->parser->parseContent($content);

        $this->assertNull($spec->notificationSpecs);
        $this->assertEmpty($spec->notificationHints);
    }

    public function test_has_structured_notifications_convenience_method(): void
    {
        $content = <<<'MD'
# Task

A task entity.

## Fields

| Name | Type | Modifiers |
|------|------|-----------|
| title | string | |

## Notifications

### Assigned

| Field | Value |
|-------|-------|
| Trigger | field_change: assigned_to |
| Title | Task assigned to you |
| Body | "{model.title}" was assigned to you. |
MD;

        $spec = $this->parser->parseContent($content);

        $this->assertTrue($spec->hasStructuredNotifications());
    }

    public function test_has_structured_notifications_false_for_legacy(): void
    {
        $content = <<<'MD'
# Task

A task entity.

## Fields

| Name | Type | Modifiers |
|------|------|-----------|
| title | string | |

## Notification Hints

- TaskAssigned: When assigned_to changes
MD;

        $spec = $this->parser->parseContent($content);

        $this->assertFalse($spec->hasStructuredNotifications());
    }

    // ========================================================================
    // Edge cases
    // ========================================================================

    public function test_empty_notifications_section_returns_null(): void
    {
        $content = <<<'MD'
# Task

A task entity.

## Fields

| Name | Type | Modifiers |
|------|------|-----------|
| title | string | |

## Notifications

Nothing here yet.
MD;

        $spec = $this->parser->parseContent($content);

        $this->assertNull($spec->notificationSpecs);
    }

    public function test_notification_missing_trigger_is_skipped(): void
    {
        $content = <<<'MD'
# Task

A task entity.

## Fields

| Name | Type | Modifiers |
|------|------|-----------|
| title | string | |

## Notifications

### NoTrigger

| Field | Value |
|-------|-------|
| Title | Missing trigger |
| Body | This has no trigger. |
MD;

        $spec = $this->parser->parseContent($content);

        $this->assertNull($spec->notificationSpecs);
    }

    public function test_notification_missing_title_is_skipped(): void
    {
        $content = <<<'MD'
# Task

A task entity.

## Fields

| Name | Type | Modifiers |
|------|------|-----------|
| title | string | |

## Notifications

### NoTitle

| Field | Value |
|-------|-------|
| Trigger | created |
| Body | This has no title. |
MD;

        $spec = $this->parser->parseContent($content);

        $this->assertNull($spec->notificationSpecs);
    }
}
