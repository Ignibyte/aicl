<?php

namespace Aicl\Tests\Unit\Console\Support;

use Aicl\Console\Support\SpecFileParser;
use PHPUnit\Framework\TestCase;

class WidgetParserTest extends TestCase
{
    protected SpecFileParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new SpecFileParser;
    }

    // ========================================================================
    // Structured ## Widgets parsing
    // ========================================================================

    public function test_parse_structured_stats_widget(): void
    {
        $content = <<<'MD'
# Invoice

An invoice entity.

## Fields

| Name | Type | Modifiers |
|------|------|-----------|
| title | string | |
| amount | float | |

## Widgets

### StatsOverview

| Metric | Query | Color | Condition Color |
|--------|-------|-------|-----------------|
| Total Invoices | count(*) | primary | |
| Active | count(*) where status = active | success | |
| Overdue | count(*) where status = overdue | danger | > 0: danger, else: success |

## Options

- widgets: true
MD;

        $spec = $this->parser->parseContent($content);

        $this->assertNotNull($spec->widgetSpecs);
        $this->assertCount(1, $spec->widgetSpecs);
        $this->assertSame('stats', $spec->widgetSpecs[0]->type);
        $this->assertCount(3, $spec->widgetSpecs[0]->metrics);
        $this->assertSame('Total Invoices', $spec->widgetSpecs[0]->metrics[0]->label);
        $this->assertSame('count(*)', $spec->widgetSpecs[0]->metrics[0]->query);
        $this->assertSame('primary', $spec->widgetSpecs[0]->metrics[0]->color);
        $this->assertNull($spec->widgetSpecs[0]->metrics[0]->conditionColor);
        $this->assertSame('> 0: danger, else: success', $spec->widgetSpecs[0]->metrics[2]->conditionColor);
    }

    public function test_parse_structured_chart_widget(): void
    {
        $content = <<<'MD'
# Task

A task entity.

## Fields

| Name | Type | Modifiers |
|------|------|-----------|
| title | string | |

## Widgets

### Chart

| Type | Group By | Colors |
|------|----------|--------|
| doughnut | status | draft:gray, active:success, completed:info |
MD;

        $spec = $this->parser->parseContent($content);

        $this->assertNotNull($spec->widgetSpecs);
        $this->assertCount(1, $spec->widgetSpecs);
        $this->assertSame('chart', $spec->widgetSpecs[0]->type);
        $this->assertSame('doughnut', $spec->widgetSpecs[0]->chartType);
        $this->assertSame('status', $spec->widgetSpecs[0]->groupBy);
        $this->assertCount(3, $spec->widgetSpecs[0]->colors);
        $this->assertSame('gray', $spec->widgetSpecs[0]->colors['draft']);
        $this->assertSame('success', $spec->widgetSpecs[0]->colors['active']);
        $this->assertSame('info', $spec->widgetSpecs[0]->colors['completed']);
    }

    public function test_parse_structured_table_widget(): void
    {
        $content = <<<'MD'
# Task

A task entity.

## Fields

| Name | Type | Modifiers |
|------|------|-----------|
| title | string | |
| due_date | date | |

## Widgets

### Table

| Name | Query | Columns |
|------|-------|---------|
| Upcoming Deadlines | where status = active, due_date >= now, order by due_date, limit 5 | title:bold, due_date:date, owner.name |
MD;

        $spec = $this->parser->parseContent($content);

        $this->assertNotNull($spec->widgetSpecs);
        $this->assertCount(1, $spec->widgetSpecs);
        $this->assertSame('table', $spec->widgetSpecs[0]->type);
        $this->assertSame('Upcoming Deadlines', $spec->widgetSpecs[0]->name);
        $this->assertSame('where status = active, due_date >= now, order by due_date, limit 5', $spec->widgetSpecs[0]->query);
        $this->assertCount(3, $spec->widgetSpecs[0]->columns);
        $this->assertSame('title', $spec->widgetSpecs[0]->columns[0]->name);
        $this->assertSame('bold', $spec->widgetSpecs[0]->columns[0]->format);
        $this->assertSame('owner.name', $spec->widgetSpecs[0]->columns[2]->name);
        $this->assertSame('', $spec->widgetSpecs[0]->columns[2]->format);
    }

    public function test_parse_all_three_widget_types(): void
    {
        $content = <<<'MD'
# Invoice

An invoice entity.

## Fields

| Name | Type | Modifiers |
|------|------|-----------|
| title | string | |
| amount | float | |
| due_date | date | |

## Widgets

### StatsOverview

| Metric | Query | Color | Condition Color |
|--------|-------|-------|-----------------|
| Total | count(*) | primary | |

### Chart

| Type | Group By | Colors |
|------|----------|--------|
| doughnut | status | draft:gray, active:success |

### Table

| Name | Query | Columns |
|------|-------|---------|
| Recent | order by created_at desc, limit 5 | title:bold, amount:money |
MD;

        $spec = $this->parser->parseContent($content);

        $this->assertNotNull($spec->widgetSpecs);
        $this->assertCount(3, $spec->widgetSpecs);
        $this->assertSame('stats', $spec->widgetSpecs[0]->type);
        $this->assertSame('chart', $spec->widgetSpecs[1]->type);
        $this->assertSame('table', $spec->widgetSpecs[2]->type);
    }

    // ========================================================================
    // Backward compatibility — legacy Widget Hints still work
    // ========================================================================

    public function test_legacy_widget_hints_no_structured_widgets(): void
    {
        $content = <<<'MD'
# Invoice

An invoice entity.

## Fields

| Name | Type | Modifiers |
|------|------|-----------|
| title | string | |

## Widget Hints

- StatsOverview: Total count, Active count
- Chart: Doughnut by status
MD;

        $spec = $this->parser->parseContent($content);

        $this->assertNull($spec->widgetSpecs);
        $this->assertCount(2, $spec->widgetHints);
        $this->assertStringContainsString('StatsOverview', $spec->widgetHints[0]);
        $this->assertStringContainsString('Chart', $spec->widgetHints[1]);
    }

    public function test_no_widgets_section_returns_null(): void
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

        $this->assertNull($spec->widgetSpecs);
        $this->assertEmpty($spec->widgetHints);
    }

    public function test_has_structured_widgets_convenience_method(): void
    {
        $content = <<<'MD'
# Task

A task entity.

## Fields

| Name | Type | Modifiers |
|------|------|-----------|
| title | string | |

## Widgets

### StatsOverview

| Metric | Query | Color | Condition Color |
|--------|-------|-------|-----------------|
| Total | count(*) | primary | |
MD;

        $spec = $this->parser->parseContent($content);

        $this->assertTrue($spec->hasStructuredWidgets());
    }

    public function test_has_structured_widgets_false_for_legacy(): void
    {
        $content = <<<'MD'
# Task

A task entity.

## Fields

| Name | Type | Modifiers |
|------|------|-----------|
| title | string | |

## Widget Hints

- StatsOverview: Total count
MD;

        $spec = $this->parser->parseContent($content);

        $this->assertFalse($spec->hasStructuredWidgets());
    }

    // ========================================================================
    // Edge cases
    // ========================================================================

    public function test_empty_widgets_section_returns_null(): void
    {
        $content = <<<'MD'
# Task

A task entity.

## Fields

| Name | Type | Modifiers |
|------|------|-----------|
| title | string | |

## Widgets

Nothing here yet.
MD;

        $spec = $this->parser->parseContent($content);

        $this->assertNull($spec->widgetSpecs);
    }

    public function test_table_widget_without_query(): void
    {
        $content = <<<'MD'
# Task

A task entity.

## Fields

| Name | Type | Modifiers |
|------|------|-----------|
| title | string | |

## Widgets

### Table

| Name | Query | Columns |
|------|-------|---------|
| Recent Tasks | | title:bold |
MD;

        $spec = $this->parser->parseContent($content);

        $this->assertNotNull($spec->widgetSpecs);
        $this->assertCount(1, $spec->widgetSpecs);
        $this->assertNull($spec->widgetSpecs[0]->query);
    }

    public function test_chart_without_colors(): void
    {
        $content = <<<'MD'
# Task

A task entity.

## Fields

| Name | Type | Modifiers |
|------|------|-----------|
| title | string | |

## Widgets

### Chart

| Type | Group By | Colors |
|------|----------|--------|
| bar | status | |
MD;

        $spec = $this->parser->parseContent($content);

        $this->assertNotNull($spec->widgetSpecs);
        $this->assertSame('bar', $spec->widgetSpecs[0]->chartType);
        $this->assertEmpty($spec->widgetSpecs[0]->colors);
    }
}
