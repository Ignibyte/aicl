<?php

namespace Aicl\Tests\Unit\Console\Support;

use Aicl\Console\Support\ReportColumnSpec;
use Aicl\Console\Support\ReportFieldSpec;
use Aicl\Console\Support\ReportLayoutSpec;
use Aicl\Console\Support\ReportSectionSpec;
use PHPUnit\Framework\TestCase;

class ReportLayoutSpecTest extends TestCase
{
    // ========================================================================
    // ReportFieldSpec
    // ========================================================================

    public function test_parse_simple_field(): void
    {
        $field = ReportFieldSpec::parse('name');

        $this->assertSame('name', $field->field);
        $this->assertNull($field->format);
        $this->assertFalse($field->isRelationship());
        $this->assertFalse($field->isTemplateVariable());
    }

    public function test_parse_field_with_format(): void
    {
        $field = ReportFieldSpec::parse('due_date:date');

        $this->assertSame('due_date', $field->field);
        $this->assertSame('date', $field->format);
    }

    public function test_parse_currency_format(): void
    {
        $field = ReportFieldSpec::parse('amount:currency');

        $this->assertSame('amount', $field->field);
        $this->assertSame('currency', $field->format);
    }

    public function test_parse_relationship_field(): void
    {
        $field = ReportFieldSpec::parse('owner.name');

        $this->assertSame('owner.name', $field->field);
        $this->assertNull($field->format);
        $this->assertTrue($field->isRelationship());
        $this->assertSame('owner', $field->relationshipName());
        $this->assertSame('name', $field->relationshipAttribute());
    }

    public function test_parse_template_variable(): void
    {
        $field = ReportFieldSpec::parse('{model.name}');

        $this->assertSame('{model.name}', $field->field);
        $this->assertTrue($field->isTemplateVariable());
        $this->assertFalse($field->isRelationship());
    }

    public function test_non_relationship_returns_null_for_relationship_methods(): void
    {
        $field = ReportFieldSpec::parse('title');

        $this->assertNull($field->relationshipName());
        $this->assertNull($field->relationshipAttribute());
    }

    // ========================================================================
    // ReportSectionSpec
    // ========================================================================

    public function test_section_type_checks(): void
    {
        $title = new ReportSectionSpec(section: 'Header', type: 'title', fields: '{model.name}');
        $this->assertTrue($title->isTitle());
        $this->assertFalse($title->isInfoGrid());

        $grid = new ReportSectionSpec(section: 'Details', type: 'info-grid', fields: 'name, email');
        $this->assertTrue($grid->isInfoGrid());
        $this->assertFalse($grid->isTitle());

        $badges = new ReportSectionSpec(section: 'Status', type: 'badges', fields: 'status, priority');
        $this->assertTrue($badges->isBadges());

        $card = new ReportSectionSpec(section: 'Notes', type: 'card', fields: 'description');
        $this->assertTrue($card->isCard());

        $timeline = new ReportSectionSpec(section: 'Activity', type: 'timeline', fields: 'activities (limit 10)');
        $this->assertTrue($timeline->isTimeline());
    }

    // ========================================================================
    // ReportColumnSpec
    // ========================================================================

    public function test_column_spec_basic(): void
    {
        $col = new ReportColumnSpec(column: 'name', format: 'text:bold', width: '25%');

        $this->assertSame('name', $col->column);
        $this->assertSame('text:bold', $col->format);
        $this->assertSame('25%', $col->width);
        $this->assertTrue($col->isBold());
        $this->assertFalse($col->isRelationship());
    }

    public function test_column_spec_relationship(): void
    {
        $col = new ReportColumnSpec(column: 'owner.name', format: 'text', width: '20%');

        $this->assertTrue($col->isRelationship());
        $this->assertSame('owner', $col->relationshipName());
        $this->assertSame('name', $col->relationshipAttribute());
    }

    public function test_column_non_relationship_returns_null(): void
    {
        $col = new ReportColumnSpec(column: 'status', format: 'badge');

        $this->assertNull($col->relationshipName());
        $this->assertNull($col->relationshipAttribute());
    }

    // ========================================================================
    // ReportLayoutSpec
    // ========================================================================

    public function test_layout_spec_has_reports(): void
    {
        $layout = new ReportLayoutSpec(
            singleReport: [new ReportSectionSpec(section: 'Header', type: 'title', fields: '{model.name}')],
            listReport: [new ReportColumnSpec(column: 'name', format: 'text')],
        );

        $this->assertTrue($layout->hasSingleReport());
        $this->assertTrue($layout->hasListReport());
    }

    public function test_empty_layout_reports(): void
    {
        $layout = new ReportLayoutSpec;

        $this->assertFalse($layout->hasSingleReport());
        $this->assertFalse($layout->hasListReport());
    }
}
