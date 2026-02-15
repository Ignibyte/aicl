<?php

namespace Aicl\Tests\Unit\Filament\Resources;

use Aicl\Filament\Resources\FailureReports\FailureReportResource;
use Aicl\Filament\Resources\FailureReports\Pages\CreateFailureReport;
use Aicl\Filament\Resources\FailureReports\Pages\EditFailureReport;
use Aicl\Filament\Resources\FailureReports\Pages\ListFailureReports;
use Aicl\Filament\Resources\FailureReports\Pages\ViewFailureReport;
use Aicl\Filament\Resources\FailureReports\Schemas\FailureReportForm;
use Aicl\Filament\Resources\FailureReports\Tables\FailureReportsTable;
use Aicl\Filament\Resources\GenerationTraces\GenerationTraceResource;
use Aicl\Filament\Resources\GenerationTraces\Pages\CreateGenerationTrace;
use Aicl\Filament\Resources\GenerationTraces\Pages\EditGenerationTrace;
use Aicl\Filament\Resources\GenerationTraces\Pages\ListGenerationTraces;
use Aicl\Filament\Resources\GenerationTraces\Pages\ViewGenerationTrace;
use Aicl\Filament\Resources\GenerationTraces\Schemas\GenerationTraceForm;
use Aicl\Filament\Resources\GenerationTraces\Tables\GenerationTracesTable;
use Aicl\Filament\Resources\PreventionRules\Pages\CreatePreventionRule;
use Aicl\Filament\Resources\PreventionRules\Pages\EditPreventionRule;
use Aicl\Filament\Resources\PreventionRules\Pages\ListPreventionRules;
use Aicl\Filament\Resources\PreventionRules\Pages\ViewPreventionRule;
use Aicl\Filament\Resources\PreventionRules\PreventionRuleResource;
use Aicl\Filament\Resources\PreventionRules\Schemas\PreventionRuleForm;
use Aicl\Filament\Resources\PreventionRules\Tables\PreventionRulesTable;
use Aicl\Filament\Resources\RlmFailures\Pages\CreateRlmFailure;
use Aicl\Filament\Resources\RlmFailures\Pages\EditRlmFailure;
use Aicl\Filament\Resources\RlmFailures\Pages\ListRlmFailures;
use Aicl\Filament\Resources\RlmFailures\Pages\ViewRlmFailure;
use Aicl\Filament\Resources\RlmFailures\RlmFailureResource;
use Aicl\Filament\Resources\RlmFailures\Schemas\RlmFailureForm;
use Aicl\Filament\Resources\RlmFailures\Tables\RlmFailuresTable;
use Aicl\Filament\Resources\RlmLessons\Pages\CreateRlmLesson;
use Aicl\Filament\Resources\RlmLessons\Pages\EditRlmLesson;
use Aicl\Filament\Resources\RlmLessons\Pages\ListRlmLessons;
use Aicl\Filament\Resources\RlmLessons\Pages\ViewRlmLesson;
use Aicl\Filament\Resources\RlmLessons\RlmLessonResource;
use Aicl\Filament\Resources\RlmLessons\Schemas\RlmLessonForm;
use Aicl\Filament\Resources\RlmLessons\Tables\RlmLessonsTable;
use Aicl\Filament\Resources\RlmPatterns\Pages\CreateRlmPattern;
use Aicl\Filament\Resources\RlmPatterns\Pages\EditRlmPattern;
use Aicl\Filament\Resources\RlmPatterns\Pages\ListRlmPatterns;
use Aicl\Filament\Resources\RlmPatterns\Pages\ViewRlmPattern;
use Aicl\Filament\Resources\RlmPatterns\RlmPatternResource;
use Aicl\Filament\Resources\RlmPatterns\Schemas\RlmPatternForm;
use Aicl\Filament\Resources\RlmPatterns\Tables\RlmPatternsTable;
use Aicl\Models\FailureReport;
use Aicl\Models\GenerationTrace;
use Aicl\Models\PreventionRule;
use Aicl\Models\RlmFailure;
use Aicl\Models\RlmLesson;
use Aicl\Models\RlmPattern;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Pages\ViewRecord;
use Filament\Resources\Resource;
use PHPUnit\Framework\TestCase;

class ResourceCoverageTest extends TestCase
{
    // ── RlmPatternResource ────────────────────────────────────────

    public function test_rlm_pattern_resource_extends_resource(): void
    {
        $this->assertTrue(is_subclass_of(RlmPatternResource::class, Resource::class));
    }

    public function test_rlm_pattern_resource_model_class(): void
    {
        $this->assertEquals(RlmPattern::class, RlmPatternResource::getModel());
    }

    public function test_rlm_pattern_resource_has_pages(): void
    {
        $pages = RlmPatternResource::getPages();
        $this->assertNotEmpty($pages);
        $this->assertArrayHasKey('index', $pages);
        $this->assertArrayHasKey('create', $pages);
        $this->assertArrayHasKey('view', $pages);
        $this->assertArrayHasKey('edit', $pages);
    }

    public function test_rlm_pattern_resource_navigation_group(): void
    {
        $reflection = new \ReflectionClass(RlmPatternResource::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertSame('RLM Hub', $defaults['navigationGroup']);
    }

    public function test_rlm_pattern_resource_has_slug(): void
    {
        $this->assertNotEmpty(RlmPatternResource::getSlug());
    }

    public function test_rlm_pattern_resource_has_form_method(): void
    {
        $this->assertTrue(method_exists(RlmPatternResource::class, 'form'));
    }

    public function test_rlm_pattern_resource_has_table_method(): void
    {
        $this->assertTrue(method_exists(RlmPatternResource::class, 'table'));
    }

    public function test_rlm_pattern_form_has_configure_method(): void
    {
        $this->assertTrue(method_exists(RlmPatternForm::class, 'configure'));

        $reflection = new \ReflectionMethod(RlmPatternForm::class, 'configure');
        $this->assertTrue($reflection->isStatic());
    }

    public function test_rlm_patterns_table_has_configure_method(): void
    {
        $this->assertTrue(method_exists(RlmPatternsTable::class, 'configure'));

        $reflection = new \ReflectionMethod(RlmPatternsTable::class, 'configure');
        $this->assertTrue($reflection->isStatic());
    }

    public function test_list_rlm_patterns_extends_list_records(): void
    {
        $this->assertTrue(is_subclass_of(ListRlmPatterns::class, ListRecords::class));
    }

    public function test_create_rlm_pattern_extends_create_record(): void
    {
        $this->assertTrue(is_subclass_of(CreateRlmPattern::class, CreateRecord::class));
    }

    public function test_edit_rlm_pattern_extends_edit_record(): void
    {
        $this->assertTrue(is_subclass_of(EditRlmPattern::class, EditRecord::class));
    }

    public function test_view_rlm_pattern_extends_view_record(): void
    {
        $this->assertTrue(is_subclass_of(ViewRlmPattern::class, ViewRecord::class));
    }

    // ── RlmFailureResource ────────────────────────────────────────

    public function test_rlm_failure_resource_extends_resource(): void
    {
        $this->assertTrue(is_subclass_of(RlmFailureResource::class, Resource::class));
    }

    public function test_rlm_failure_resource_model_class(): void
    {
        $this->assertEquals(RlmFailure::class, RlmFailureResource::getModel());
    }

    public function test_rlm_failure_resource_has_pages(): void
    {
        $pages = RlmFailureResource::getPages();
        $this->assertNotEmpty($pages);
        $this->assertArrayHasKey('index', $pages);
        $this->assertArrayHasKey('create', $pages);
        $this->assertArrayHasKey('view', $pages);
        $this->assertArrayHasKey('edit', $pages);
    }

    public function test_rlm_failure_resource_navigation_group(): void
    {
        $reflection = new \ReflectionClass(RlmFailureResource::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertSame('RLM Hub', $defaults['navigationGroup']);
    }

    public function test_rlm_failure_resource_has_slug(): void
    {
        $this->assertNotEmpty(RlmFailureResource::getSlug());
    }

    public function test_rlm_failure_form_has_configure_method(): void
    {
        $this->assertTrue(method_exists(RlmFailureForm::class, 'configure'));

        $reflection = new \ReflectionMethod(RlmFailureForm::class, 'configure');
        $this->assertTrue($reflection->isStatic());
    }

    public function test_rlm_failures_table_has_configure_method(): void
    {
        $this->assertTrue(method_exists(RlmFailuresTable::class, 'configure'));

        $reflection = new \ReflectionMethod(RlmFailuresTable::class, 'configure');
        $this->assertTrue($reflection->isStatic());
    }

    public function test_list_rlm_failures_extends_list_records(): void
    {
        $this->assertTrue(is_subclass_of(ListRlmFailures::class, ListRecords::class));
    }

    public function test_create_rlm_failure_extends_create_record(): void
    {
        $this->assertTrue(is_subclass_of(CreateRlmFailure::class, CreateRecord::class));
    }

    public function test_edit_rlm_failure_extends_edit_record(): void
    {
        $this->assertTrue(is_subclass_of(EditRlmFailure::class, EditRecord::class));
    }

    public function test_view_rlm_failure_extends_view_record(): void
    {
        $this->assertTrue(is_subclass_of(ViewRlmFailure::class, ViewRecord::class));
    }

    // ── RlmLessonResource ─────────────────────────────────────────

    public function test_rlm_lesson_resource_extends_resource(): void
    {
        $this->assertTrue(is_subclass_of(RlmLessonResource::class, Resource::class));
    }

    public function test_rlm_lesson_resource_model_class(): void
    {
        $this->assertEquals(RlmLesson::class, RlmLessonResource::getModel());
    }

    public function test_rlm_lesson_resource_has_pages(): void
    {
        $pages = RlmLessonResource::getPages();
        $this->assertNotEmpty($pages);
        $this->assertArrayHasKey('index', $pages);
        $this->assertArrayHasKey('create', $pages);
        $this->assertArrayHasKey('view', $pages);
        $this->assertArrayHasKey('edit', $pages);
    }

    public function test_rlm_lesson_resource_navigation_group(): void
    {
        $reflection = new \ReflectionClass(RlmLessonResource::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertSame('RLM Hub', $defaults['navigationGroup']);
    }

    public function test_rlm_lesson_resource_has_slug(): void
    {
        $this->assertNotEmpty(RlmLessonResource::getSlug());
    }

    public function test_rlm_lesson_form_has_configure_method(): void
    {
        $this->assertTrue(method_exists(RlmLessonForm::class, 'configure'));

        $reflection = new \ReflectionMethod(RlmLessonForm::class, 'configure');
        $this->assertTrue($reflection->isStatic());
    }

    public function test_rlm_lessons_table_has_configure_method(): void
    {
        $this->assertTrue(method_exists(RlmLessonsTable::class, 'configure'));

        $reflection = new \ReflectionMethod(RlmLessonsTable::class, 'configure');
        $this->assertTrue($reflection->isStatic());
    }

    public function test_list_rlm_lessons_extends_list_records(): void
    {
        $this->assertTrue(is_subclass_of(ListRlmLessons::class, ListRecords::class));
    }

    public function test_create_rlm_lesson_extends_create_record(): void
    {
        $this->assertTrue(is_subclass_of(CreateRlmLesson::class, CreateRecord::class));
    }

    public function test_edit_rlm_lesson_extends_edit_record(): void
    {
        $this->assertTrue(is_subclass_of(EditRlmLesson::class, EditRecord::class));
    }

    public function test_view_rlm_lesson_extends_view_record(): void
    {
        $this->assertTrue(is_subclass_of(ViewRlmLesson::class, ViewRecord::class));
    }

    // ── FailureReportResource ─────────────────────────────────────

    public function test_failure_report_resource_extends_resource(): void
    {
        $this->assertTrue(is_subclass_of(FailureReportResource::class, Resource::class));
    }

    public function test_failure_report_resource_model_class(): void
    {
        $this->assertEquals(FailureReport::class, FailureReportResource::getModel());
    }

    public function test_failure_report_resource_has_pages(): void
    {
        $pages = FailureReportResource::getPages();
        $this->assertNotEmpty($pages);
        $this->assertArrayHasKey('index', $pages);
        $this->assertArrayHasKey('create', $pages);
        $this->assertArrayHasKey('view', $pages);
        $this->assertArrayHasKey('edit', $pages);
    }

    public function test_failure_report_resource_navigation_group(): void
    {
        $reflection = new \ReflectionClass(FailureReportResource::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertSame('RLM Hub', $defaults['navigationGroup']);
    }

    public function test_failure_report_resource_has_slug(): void
    {
        $this->assertNotEmpty(FailureReportResource::getSlug());
    }

    public function test_failure_report_form_has_configure_method(): void
    {
        $this->assertTrue(method_exists(FailureReportForm::class, 'configure'));

        $reflection = new \ReflectionMethod(FailureReportForm::class, 'configure');
        $this->assertTrue($reflection->isStatic());
    }

    public function test_failure_reports_table_has_configure_method(): void
    {
        $this->assertTrue(method_exists(FailureReportsTable::class, 'configure'));

        $reflection = new \ReflectionMethod(FailureReportsTable::class, 'configure');
        $this->assertTrue($reflection->isStatic());
    }

    public function test_list_failure_reports_extends_list_records(): void
    {
        $this->assertTrue(is_subclass_of(ListFailureReports::class, ListRecords::class));
    }

    public function test_create_failure_report_extends_create_record(): void
    {
        $this->assertTrue(is_subclass_of(CreateFailureReport::class, CreateRecord::class));
    }

    public function test_edit_failure_report_extends_edit_record(): void
    {
        $this->assertTrue(is_subclass_of(EditFailureReport::class, EditRecord::class));
    }

    public function test_view_failure_report_extends_view_record(): void
    {
        $this->assertTrue(is_subclass_of(ViewFailureReport::class, ViewRecord::class));
    }

    // ── GenerationTraceResource ───────────────────────────────────

    public function test_generation_trace_resource_extends_resource(): void
    {
        $this->assertTrue(is_subclass_of(GenerationTraceResource::class, Resource::class));
    }

    public function test_generation_trace_resource_model_class(): void
    {
        $this->assertEquals(GenerationTrace::class, GenerationTraceResource::getModel());
    }

    public function test_generation_trace_resource_has_pages(): void
    {
        $pages = GenerationTraceResource::getPages();
        $this->assertNotEmpty($pages);
        $this->assertArrayHasKey('index', $pages);
        $this->assertArrayHasKey('create', $pages);
        $this->assertArrayHasKey('view', $pages);
        $this->assertArrayHasKey('edit', $pages);
    }

    public function test_generation_trace_resource_navigation_group(): void
    {
        $reflection = new \ReflectionClass(GenerationTraceResource::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertSame('RLM Hub', $defaults['navigationGroup']);
    }

    public function test_generation_trace_resource_has_slug(): void
    {
        $this->assertNotEmpty(GenerationTraceResource::getSlug());
    }

    public function test_generation_trace_form_has_configure_method(): void
    {
        $this->assertTrue(method_exists(GenerationTraceForm::class, 'configure'));

        $reflection = new \ReflectionMethod(GenerationTraceForm::class, 'configure');
        $this->assertTrue($reflection->isStatic());
    }

    public function test_generation_traces_table_has_configure_method(): void
    {
        $this->assertTrue(method_exists(GenerationTracesTable::class, 'configure'));

        $reflection = new \ReflectionMethod(GenerationTracesTable::class, 'configure');
        $this->assertTrue($reflection->isStatic());
    }

    public function test_list_generation_traces_extends_list_records(): void
    {
        $this->assertTrue(is_subclass_of(ListGenerationTraces::class, ListRecords::class));
    }

    public function test_create_generation_trace_extends_create_record(): void
    {
        $this->assertTrue(is_subclass_of(CreateGenerationTrace::class, CreateRecord::class));
    }

    public function test_edit_generation_trace_extends_edit_record(): void
    {
        $this->assertTrue(is_subclass_of(EditGenerationTrace::class, EditRecord::class));
    }

    public function test_view_generation_trace_extends_view_record(): void
    {
        $this->assertTrue(is_subclass_of(ViewGenerationTrace::class, ViewRecord::class));
    }

    // ── PreventionRuleResource ────────────────────────────────────

    public function test_prevention_rule_resource_extends_resource(): void
    {
        $this->assertTrue(is_subclass_of(PreventionRuleResource::class, Resource::class));
    }

    public function test_prevention_rule_resource_model_class(): void
    {
        $this->assertEquals(PreventionRule::class, PreventionRuleResource::getModel());
    }

    public function test_prevention_rule_resource_has_pages(): void
    {
        $pages = PreventionRuleResource::getPages();
        $this->assertNotEmpty($pages);
        $this->assertArrayHasKey('index', $pages);
        $this->assertArrayHasKey('create', $pages);
        $this->assertArrayHasKey('view', $pages);
        $this->assertArrayHasKey('edit', $pages);
    }

    public function test_prevention_rule_resource_navigation_group(): void
    {
        $reflection = new \ReflectionClass(PreventionRuleResource::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertSame('RLM Hub', $defaults['navigationGroup']);
    }

    public function test_prevention_rule_resource_has_slug(): void
    {
        $this->assertNotEmpty(PreventionRuleResource::getSlug());
    }

    public function test_prevention_rule_form_has_configure_method(): void
    {
        $this->assertTrue(method_exists(PreventionRuleForm::class, 'configure'));

        $reflection = new \ReflectionMethod(PreventionRuleForm::class, 'configure');
        $this->assertTrue($reflection->isStatic());
    }

    public function test_prevention_rules_table_has_configure_method(): void
    {
        $this->assertTrue(method_exists(PreventionRulesTable::class, 'configure'));

        $reflection = new \ReflectionMethod(PreventionRulesTable::class, 'configure');
        $this->assertTrue($reflection->isStatic());
    }

    public function test_list_prevention_rules_extends_list_records(): void
    {
        $this->assertTrue(is_subclass_of(ListPreventionRules::class, ListRecords::class));
    }

    public function test_create_prevention_rule_extends_create_record(): void
    {
        $this->assertTrue(is_subclass_of(CreatePreventionRule::class, CreateRecord::class));
    }

    public function test_edit_prevention_rule_extends_edit_record(): void
    {
        $this->assertTrue(is_subclass_of(EditPreventionRule::class, EditRecord::class));
    }

    public function test_view_prevention_rule_extends_view_record(): void
    {
        $this->assertTrue(is_subclass_of(ViewPreventionRule::class, ViewRecord::class));
    }

    // ── Navigation Sort Ordering ──────────────────────────────────

    public function test_rlm_pattern_navigation_sort(): void
    {
        $reflection = new \ReflectionClass(RlmPatternResource::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertSame(1, $defaults['navigationSort']);
    }

    public function test_rlm_failure_navigation_sort(): void
    {
        $reflection = new \ReflectionClass(RlmFailureResource::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertSame(2, $defaults['navigationSort']);
    }

    public function test_failure_report_navigation_sort(): void
    {
        $reflection = new \ReflectionClass(FailureReportResource::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertSame(3, $defaults['navigationSort']);
    }

    public function test_rlm_lesson_navigation_sort(): void
    {
        $reflection = new \ReflectionClass(RlmLessonResource::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertSame(4, $defaults['navigationSort']);
    }

    public function test_generation_trace_navigation_sort(): void
    {
        $reflection = new \ReflectionClass(GenerationTraceResource::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertSame(5, $defaults['navigationSort']);
    }

    public function test_prevention_rule_navigation_sort(): void
    {
        $reflection = new \ReflectionClass(PreventionRuleResource::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertSame(6, $defaults['navigationSort']);
    }

    // ── Record Title Attributes ───────────────────────────────────

    public function test_rlm_pattern_record_title_attribute(): void
    {
        $reflection = new \ReflectionClass(RlmPatternResource::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertSame('name', $defaults['recordTitleAttribute']);
    }

    public function test_rlm_failure_record_title_attribute(): void
    {
        $reflection = new \ReflectionClass(RlmFailureResource::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertSame('title', $defaults['recordTitleAttribute']);
    }

    public function test_rlm_lesson_record_title_attribute(): void
    {
        $reflection = new \ReflectionClass(RlmLessonResource::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertSame('summary', $defaults['recordTitleAttribute']);
    }

    public function test_failure_report_record_title_attribute(): void
    {
        $reflection = new \ReflectionClass(FailureReportResource::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertSame('entity_name', $defaults['recordTitleAttribute']);
    }

    public function test_generation_trace_record_title_attribute(): void
    {
        $reflection = new \ReflectionClass(GenerationTraceResource::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertSame('entity_name', $defaults['recordTitleAttribute']);
    }

    public function test_prevention_rule_record_title_attribute(): void
    {
        $reflection = new \ReflectionClass(PreventionRuleResource::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertSame('rule_text', $defaults['recordTitleAttribute']);
    }
}
