<?php

namespace Aicl\Tests\Unit\Console\Support;

use Aicl\Console\Support\SpecFileParser;
use PHPUnit\Framework\TestCase;

class ReportLayoutParserTest extends TestCase
{
    protected SpecFileParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new SpecFileParser;
    }

    // ========================================================================
    // Structured ## Report Layout parsing
    // ========================================================================

    public function test_parse_single_report_sections(): void
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

## Report Layout

### Single Report

| Section | Type | Fields |
|---------|------|--------|
| Header | title | {model.title} |
| Header | badges | status, priority |
| Details | info-grid | invoice_number, owner.name, due_date:date, amount:currency |
| Description | card | description |
| Activity | timeline | activities (limit 10) |
MD;

        $spec = $this->parser->parseContent($content);

        $this->assertNotNull($spec->reportLayout);
        $this->assertTrue($spec->hasReportLayout());
        $this->assertTrue($spec->reportLayout->hasSingleReport());
        $this->assertCount(5, $spec->reportLayout->singleReport);

        // Title section
        $title = $spec->reportLayout->singleReport[0];
        $this->assertSame('Header', $title->section);
        $this->assertSame('title', $title->type);
        $this->assertTrue($title->isTitle());
        $this->assertCount(1, $title->parsedFields);
        $this->assertTrue($title->parsedFields[0]->isTemplateVariable());

        // Badges section
        $badges = $spec->reportLayout->singleReport[1];
        $this->assertSame('badges', $badges->type);
        $this->assertCount(2, $badges->parsedFields);

        // Info-grid section
        $grid = $spec->reportLayout->singleReport[2];
        $this->assertSame('info-grid', $grid->type);
        $this->assertCount(4, $grid->parsedFields);
        $this->assertSame('date', $grid->parsedFields[2]->format);
        $this->assertSame('currency', $grid->parsedFields[3]->format);

        // Card section
        $card = $spec->reportLayout->singleReport[3];
        $this->assertTrue($card->isCard());

        // Timeline section
        $timeline = $spec->reportLayout->singleReport[4];
        $this->assertTrue($timeline->isTimeline());
    }

    public function test_parse_list_report_columns(): void
    {
        $content = <<<'MD'
# Invoice

An invoice entity.

## Fields

| Name | Type | Modifiers |
|------|------|-----------|
| title | string | |
| amount | float | |

## Report Layout

### List Report

| Column | Format | Width |
|--------|--------|-------|
| invoice_number | text | 15% |
| name | text:bold | 25% |
| status | badge | 10% |
| amount | currency | 15% |
| due_date | date | 15% |
| owner.name | text | 20% |
MD;

        $spec = $this->parser->parseContent($content);

        $this->assertNotNull($spec->reportLayout);
        $this->assertTrue($spec->reportLayout->hasListReport());
        $this->assertCount(6, $spec->reportLayout->listReport);

        $this->assertSame('invoice_number', $spec->reportLayout->listReport[0]->column);
        $this->assertSame('text', $spec->reportLayout->listReport[0]->format);
        $this->assertSame('15%', $spec->reportLayout->listReport[0]->width);

        $this->assertSame('name', $spec->reportLayout->listReport[1]->column);
        $this->assertTrue($spec->reportLayout->listReport[1]->isBold());
        $this->assertSame('25%', $spec->reportLayout->listReport[1]->width);

        $this->assertSame('owner.name', $spec->reportLayout->listReport[5]->column);
        $this->assertTrue($spec->reportLayout->listReport[5]->isRelationship());
    }

    public function test_parse_both_single_and_list_reports(): void
    {
        $content = <<<'MD'
# Invoice

An invoice entity.

## Fields

| Name | Type | Modifiers |
|------|------|-----------|
| title | string | |

## Report Layout

### Single Report

| Section | Type | Fields |
|---------|------|--------|
| Header | title | {model.title} |

### List Report

| Column | Format | Width |
|--------|--------|-------|
| name | text:bold | 50% |
| status | badge | 50% |
MD;

        $spec = $this->parser->parseContent($content);

        $this->assertNotNull($spec->reportLayout);
        $this->assertTrue($spec->reportLayout->hasSingleReport());
        $this->assertTrue($spec->reportLayout->hasListReport());
        $this->assertCount(1, $spec->reportLayout->singleReport);
        $this->assertCount(2, $spec->reportLayout->listReport);
    }

    // ========================================================================
    // Backward compatibility
    // ========================================================================

    public function test_no_report_layout_returns_null(): void
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

        $this->assertNull($spec->reportLayout);
        $this->assertFalse($spec->hasReportLayout());
    }

    public function test_has_report_layout_convenience_method(): void
    {
        $content = <<<'MD'
# Task

A task entity.

## Fields

| Name | Type | Modifiers |
|------|------|-----------|
| title | string | |

## Report Layout

### Single Report

| Section | Type | Fields |
|---------|------|--------|
| Header | title | {model.title} |
MD;

        $spec = $this->parser->parseContent($content);

        $this->assertTrue($spec->hasReportLayout());
    }

    // ========================================================================
    // Edge cases
    // ========================================================================

    public function test_empty_report_layout_returns_null(): void
    {
        $content = <<<'MD'
# Task

A task entity.

## Fields

| Name | Type | Modifiers |
|------|------|-----------|
| title | string | |

## Report Layout

Nothing here yet.
MD;

        $spec = $this->parser->parseContent($content);

        $this->assertNull($spec->reportLayout);
    }

    public function test_section_with_empty_fields_is_skipped(): void
    {
        $content = <<<'MD'
# Task

A task entity.

## Fields

| Name | Type | Modifiers |
|------|------|-----------|
| title | string | |

## Report Layout

### Single Report

| Section | Type | Fields |
|---------|------|--------|
| Header | title | {model.title} |
| Empty | info-grid | |
MD;

        $spec = $this->parser->parseContent($content);

        $this->assertNotNull($spec->reportLayout);
        $this->assertCount(1, $spec->reportLayout->singleReport);
    }

    public function test_list_report_column_without_width(): void
    {
        $content = <<<'MD'
# Task

A task entity.

## Fields

| Name | Type | Modifiers |
|------|------|-----------|
| title | string | |

## Report Layout

### List Report

| Column | Format | Width |
|--------|--------|-------|
| name | text | |
| status | badge | 15% |
MD;

        $spec = $this->parser->parseContent($content);

        $this->assertNotNull($spec->reportLayout);
        $this->assertCount(2, $spec->reportLayout->listReport);
        $this->assertSame('', $spec->reportLayout->listReport[0]->width);
        $this->assertSame('15%', $spec->reportLayout->listReport[1]->width);
    }

    public function test_single_report_with_relationship_field(): void
    {
        $content = <<<'MD'
# Task

A task entity.

## Fields

| Name | Type | Modifiers |
|------|------|-----------|
| title | string | |

## Report Layout

### Single Report

| Section | Type | Fields |
|---------|------|--------|
| Details | info-grid | title, owner.name, created_at:date |
MD;

        $spec = $this->parser->parseContent($content);

        $this->assertNotNull($spec->reportLayout);
        $grid = $spec->reportLayout->singleReport[0];
        $this->assertCount(3, $grid->parsedFields);
        $this->assertFalse($grid->parsedFields[0]->isRelationship());
        $this->assertTrue($grid->parsedFields[1]->isRelationship());
        $this->assertSame('owner', $grid->parsedFields[1]->relationshipName());
        $this->assertSame('date', $grid->parsedFields[2]->format);
    }
}
