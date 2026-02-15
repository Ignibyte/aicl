<?php

namespace Aicl\Tests\Unit\Filament\Exporters;

use Aicl\Filament\Exporters\FailureReportExporter;
use Aicl\Filament\Exporters\GenerationTraceExporter;
use Aicl\Filament\Exporters\PreventionRuleExporter;
use Aicl\Filament\Exporters\RlmFailureExporter;
use Aicl\Filament\Exporters\RlmLessonExporter;
use Aicl\Filament\Exporters\RlmPatternExporter;
use Aicl\Models\FailureReport;
use Aicl\Models\GenerationTrace;
use Aicl\Models\PreventionRule;
use Aicl\Models\RlmFailure;
use Aicl\Models\RlmLesson;
use Aicl\Models\RlmPattern;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Tests\TestCase;

class ExporterTest extends TestCase
{
    // ── FailureReportExporter ────────────────────────────────────────

    public function test_failure_report_exporter_extends_exporter(): void
    {
        $this->assertTrue(is_subclass_of(FailureReportExporter::class, Exporter::class));
    }

    public function test_failure_report_exporter_model_is_failure_report(): void
    {
        $reflection = new \ReflectionClass(FailureReportExporter::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertEquals(FailureReport::class, $defaults['model']);
    }

    public function test_failure_report_exporter_has_columns(): void
    {
        $columns = FailureReportExporter::getColumns();

        $this->assertIsArray($columns);
        $this->assertNotEmpty($columns);
    }

    public function test_failure_report_exporter_columns_include_expected_fields(): void
    {
        $columns = FailureReportExporter::getColumns();
        $names = array_map(fn ($col) => $col->getName(), $columns);

        $this->assertContains('id', $names);
        $this->assertContains('entity_name', $names);
        $this->assertContains('phase', $names);
        $this->assertContains('created_at', $names);
    }

    public function test_failure_report_exporter_completed_notification_body(): void
    {
        $export = new Export;
        $export->successful_rows = 15;

        $body = FailureReportExporter::getCompletedNotificationBody($export);

        $this->assertStringContainsString('15', $body);
        $this->assertStringContainsString('failure report export', $body);
    }

    // ── GenerationTraceExporter ──────────────────────────────────────

    public function test_generation_trace_exporter_extends_exporter(): void
    {
        $this->assertTrue(is_subclass_of(GenerationTraceExporter::class, Exporter::class));
    }

    public function test_generation_trace_exporter_model_is_generation_trace(): void
    {
        $reflection = new \ReflectionClass(GenerationTraceExporter::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertEquals(GenerationTrace::class, $defaults['model']);
    }

    public function test_generation_trace_exporter_has_columns(): void
    {
        $columns = GenerationTraceExporter::getColumns();

        $this->assertIsArray($columns);
        $this->assertNotEmpty($columns);
    }

    public function test_generation_trace_exporter_columns_include_expected_fields(): void
    {
        $columns = GenerationTraceExporter::getColumns();
        $names = array_map(fn ($col) => $col->getName(), $columns);

        $this->assertContains('id', $names);
        $this->assertContains('entity_name', $names);
        $this->assertContains('structural_score', $names);
        $this->assertContains('created_at', $names);
    }

    public function test_generation_trace_exporter_completed_notification_body(): void
    {
        $export = new Export;
        $export->successful_rows = 8;

        $body = GenerationTraceExporter::getCompletedNotificationBody($export);

        $this->assertStringContainsString('8', $body);
        $this->assertStringContainsString('GenerationTrace export', $body);
    }

    // ── PreventionRuleExporter ───────────────────────────────────────

    public function test_prevention_rule_exporter_extends_exporter(): void
    {
        $this->assertTrue(is_subclass_of(PreventionRuleExporter::class, Exporter::class));
    }

    public function test_prevention_rule_exporter_model_is_prevention_rule(): void
    {
        $reflection = new \ReflectionClass(PreventionRuleExporter::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertEquals(PreventionRule::class, $defaults['model']);
    }

    public function test_prevention_rule_exporter_has_columns(): void
    {
        $columns = PreventionRuleExporter::getColumns();

        $this->assertIsArray($columns);
        $this->assertNotEmpty($columns);
    }

    public function test_prevention_rule_exporter_columns_include_expected_fields(): void
    {
        $columns = PreventionRuleExporter::getColumns();
        $names = array_map(fn ($col) => $col->getName(), $columns);

        $this->assertContains('id', $names);
        $this->assertContains('rule_text', $names);
        $this->assertContains('confidence', $names);
        $this->assertContains('created_at', $names);
    }

    public function test_prevention_rule_exporter_completed_notification_body(): void
    {
        $export = new Export;
        $export->successful_rows = 25;

        $body = PreventionRuleExporter::getCompletedNotificationBody($export);

        $this->assertStringContainsString('25', $body);
        $this->assertStringContainsString('PreventionRule export', $body);
    }

    // ── RlmFailureExporter ───────────────────────────────────────────

    public function test_rlm_failure_exporter_extends_exporter(): void
    {
        $this->assertTrue(is_subclass_of(RlmFailureExporter::class, Exporter::class));
    }

    public function test_rlm_failure_exporter_model_is_rlm_failure(): void
    {
        $reflection = new \ReflectionClass(RlmFailureExporter::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertEquals(RlmFailure::class, $defaults['model']);
    }

    public function test_rlm_failure_exporter_has_columns(): void
    {
        $columns = RlmFailureExporter::getColumns();

        $this->assertIsArray($columns);
        $this->assertNotEmpty($columns);
    }

    public function test_rlm_failure_exporter_columns_include_expected_fields(): void
    {
        $columns = RlmFailureExporter::getColumns();
        $names = array_map(fn ($col) => $col->getName(), $columns);

        $this->assertContains('id', $names);
        $this->assertContains('failure_code', $names);
        $this->assertContains('title', $names);
        $this->assertContains('severity', $names);
        $this->assertContains('created_at', $names);
    }

    public function test_rlm_failure_exporter_completed_notification_body(): void
    {
        $export = new Export;
        $export->successful_rows = 100;

        $body = RlmFailureExporter::getCompletedNotificationBody($export);

        $this->assertStringContainsString('100', $body);
        $this->assertStringContainsString('RlmFailure export', $body);
    }

    // ── RlmLessonExporter ────────────────────────────────────────────

    public function test_rlm_lesson_exporter_extends_exporter(): void
    {
        $this->assertTrue(is_subclass_of(RlmLessonExporter::class, Exporter::class));
    }

    public function test_rlm_lesson_exporter_model_is_rlm_lesson(): void
    {
        $reflection = new \ReflectionClass(RlmLessonExporter::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertEquals(RlmLesson::class, $defaults['model']);
    }

    public function test_rlm_lesson_exporter_has_columns(): void
    {
        $columns = RlmLessonExporter::getColumns();

        $this->assertIsArray($columns);
        $this->assertNotEmpty($columns);
    }

    public function test_rlm_lesson_exporter_columns_include_expected_fields(): void
    {
        $columns = RlmLessonExporter::getColumns();
        $names = array_map(fn ($col) => $col->getName(), $columns);

        $this->assertContains('id', $names);
        $this->assertContains('topic', $names);
        $this->assertContains('summary', $names);
        $this->assertContains('created_at', $names);
    }

    public function test_rlm_lesson_exporter_completed_notification_body(): void
    {
        $export = new Export;
        $export->successful_rows = 5;

        $body = RlmLessonExporter::getCompletedNotificationBody($export);

        $this->assertStringContainsString('5', $body);
        $this->assertStringContainsString('RlmLesson export', $body);
    }

    // ── RlmPatternExporter ───────────────────────────────────────────

    public function test_rlm_pattern_exporter_extends_exporter(): void
    {
        $this->assertTrue(is_subclass_of(RlmPatternExporter::class, Exporter::class));
    }

    public function test_rlm_pattern_exporter_model_is_rlm_pattern(): void
    {
        $reflection = new \ReflectionClass(RlmPatternExporter::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertEquals(RlmPattern::class, $defaults['model']);
    }

    public function test_rlm_pattern_exporter_has_columns(): void
    {
        $columns = RlmPatternExporter::getColumns();

        $this->assertIsArray($columns);
        $this->assertNotEmpty($columns);
    }

    public function test_rlm_pattern_exporter_columns_include_expected_fields(): void
    {
        $columns = RlmPatternExporter::getColumns();
        $names = array_map(fn ($col) => $col->getName(), $columns);

        $this->assertContains('id', $names);
        $this->assertContains('name', $names);
        $this->assertContains('check_regex', $names);
        $this->assertContains('severity', $names);
        $this->assertContains('created_at', $names);
    }

    public function test_rlm_pattern_exporter_completed_notification_body(): void
    {
        $export = new Export;
        $export->successful_rows = 42;

        $body = RlmPatternExporter::getCompletedNotificationBody($export);

        $this->assertStringContainsString('42', $body);
        $this->assertStringContainsString('RlmPattern export', $body);
    }
}
