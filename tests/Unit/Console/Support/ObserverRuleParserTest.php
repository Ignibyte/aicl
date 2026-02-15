<?php

namespace Aicl\Tests\Unit\Console\Support;

use Aicl\Console\Support\SpecFileParser;
use PHPUnit\Framework\TestCase;

class ObserverRuleParserTest extends TestCase
{
    protected SpecFileParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new SpecFileParser;
    }

    // ========================================================================
    // Structured ## Observer Rules parsing
    // ========================================================================

    public function test_parse_on_create_rules(): void
    {
        $content = <<<'MD'
# Invoice

An invoice entity.

## Fields

| Name | Type | Modifiers |
|------|------|-----------|
| title | string | |

## Observer Rules

### On Create

| Action | Details |
|--------|---------|
| log | Invoice "{model.name}" was created |
| notify | owner: InvoiceAssigned (if owner_id set) |
MD;

        $spec = $this->parser->parseContent($content);

        $this->assertNotNull($spec->observerRules);
        $this->assertCount(2, $spec->observerRules);

        $this->assertSame('created', $spec->observerRules[0]->event);
        $this->assertSame('log', $spec->observerRules[0]->action);
        $this->assertStringContainsString('{model.name}', $spec->observerRules[0]->details);
        $this->assertNull($spec->observerRules[0]->watchField);

        $this->assertSame('created', $spec->observerRules[1]->event);
        $this->assertSame('notify', $spec->observerRules[1]->action);
    }

    public function test_parse_on_update_rules(): void
    {
        $content = <<<'MD'
# Invoice

An invoice entity.

## Fields

| Name | Type | Modifiers |
|------|------|-----------|
| title | string | |
| owner_id | foreignId:users | |

## Observer Rules

### On Update

| Watch Field | Action | Details |
|-------------|--------|---------|
| owner_id | notify | new owner: InvoiceAssigned |
| status | log | Invoice status changed to {new.status.label} |
| status | notify | owner: InvoiceStatusChanged |
MD;

        $spec = $this->parser->parseContent($content);

        $this->assertNotNull($spec->observerRules);
        $this->assertCount(3, $spec->observerRules);

        $this->assertSame('updated', $spec->observerRules[0]->event);
        $this->assertSame('owner_id', $spec->observerRules[0]->watchField);
        $this->assertSame('notify', $spec->observerRules[0]->action);

        $this->assertSame('updated', $spec->observerRules[1]->event);
        $this->assertSame('status', $spec->observerRules[1]->watchField);
        $this->assertSame('log', $spec->observerRules[1]->action);

        $this->assertSame('updated', $spec->observerRules[2]->event);
        $this->assertSame('status', $spec->observerRules[2]->watchField);
        $this->assertSame('notify', $spec->observerRules[2]->action);
    }

    public function test_parse_on_delete_rules(): void
    {
        $content = <<<'MD'
# Invoice

An invoice entity.

## Fields

| Name | Type | Modifiers |
|------|------|-----------|
| title | string | |

## Observer Rules

### On Delete

| Action | Details |
|--------|---------|
| log | Invoice "{model.name}" was deleted |
MD;

        $spec = $this->parser->parseContent($content);

        $this->assertNotNull($spec->observerRules);
        $this->assertCount(1, $spec->observerRules);
        $this->assertSame('deleted', $spec->observerRules[0]->event);
        $this->assertSame('log', $spec->observerRules[0]->action);
    }

    public function test_parse_all_three_events(): void
    {
        $content = <<<'MD'
# Invoice

An invoice entity.

## Fields

| Name | Type | Modifiers |
|------|------|-----------|
| title | string | |
| owner_id | foreignId:users | |

## Observer Rules

### On Create

| Action | Details |
|--------|---------|
| log | Invoice "{model.name}" was created |

### On Update

| Watch Field | Action | Details |
|-------------|--------|---------|
| owner_id | notify | new owner: InvoiceAssigned |

### On Delete

| Action | Details |
|--------|---------|
| log | Invoice "{model.name}" was deleted |
MD;

        $spec = $this->parser->parseContent($content);

        $this->assertNotNull($spec->observerRules);
        $this->assertCount(3, $spec->observerRules);
        $this->assertSame('created', $spec->observerRules[0]->event);
        $this->assertSame('updated', $spec->observerRules[1]->event);
        $this->assertSame('deleted', $spec->observerRules[2]->event);
    }

    // ========================================================================
    // Backward compatibility — no Observer Rules section
    // ========================================================================

    public function test_no_observer_rules_returns_null(): void
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

        $this->assertNull($spec->observerRules);
        $this->assertFalse($spec->hasObserverRules());
    }

    public function test_has_observer_rules_convenience_method(): void
    {
        $content = <<<'MD'
# Task

A task entity.

## Fields

| Name | Type | Modifiers |
|------|------|-----------|
| title | string | |

## Observer Rules

### On Create

| Action | Details |
|--------|---------|
| log | Task "{model.title}" was created |
MD;

        $spec = $this->parser->parseContent($content);

        $this->assertTrue($spec->hasObserverRules());
    }

    // ========================================================================
    // Edge cases
    // ========================================================================

    public function test_empty_observer_rules_section_returns_null(): void
    {
        $content = <<<'MD'
# Task

A task entity.

## Fields

| Name | Type | Modifiers |
|------|------|-----------|
| title | string | |

## Observer Rules

Nothing here yet.
MD;

        $spec = $this->parser->parseContent($content);

        $this->assertNull($spec->observerRules);
    }

    public function test_unknown_event_section_is_skipped(): void
    {
        $content = <<<'MD'
# Task

A task entity.

## Fields

| Name | Type | Modifiers |
|------|------|-----------|
| title | string | |

## Observer Rules

### On Create

| Action | Details |
|--------|---------|
| log | Task created |

### On Restore

| Action | Details |
|--------|---------|
| log | Task restored |
MD;

        $spec = $this->parser->parseContent($content);

        $this->assertNotNull($spec->observerRules);
        $this->assertCount(1, $spec->observerRules);
        $this->assertSame('created', $spec->observerRules[0]->event);
    }

    public function test_rule_with_empty_details_is_skipped(): void
    {
        $content = <<<'MD'
# Task

A task entity.

## Fields

| Name | Type | Modifiers |
|------|------|-----------|
| title | string | |

## Observer Rules

### On Create

| Action | Details |
|--------|---------|
| log | Task created |
| log | |
MD;

        $spec = $this->parser->parseContent($content);

        $this->assertNotNull($spec->observerRules);
        $this->assertCount(1, $spec->observerRules);
    }

    public function test_update_rule_without_watch_field(): void
    {
        $content = <<<'MD'
# Task

A task entity.

## Fields

| Name | Type | Modifiers |
|------|------|-----------|
| title | string | |

## Observer Rules

### On Update

| Watch Field | Action | Details |
|-------------|--------|---------|
| | notify | owner: TaskUpdated |
MD;

        $spec = $this->parser->parseContent($content);

        $this->assertNotNull($spec->observerRules);
        $this->assertCount(1, $spec->observerRules);
        $this->assertNull($spec->observerRules[0]->watchField);
    }
}
