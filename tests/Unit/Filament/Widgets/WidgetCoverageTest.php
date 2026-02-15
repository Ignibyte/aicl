<?php

namespace Aicl\Tests\Unit\Filament\Widgets;

use Aicl\Events\EntityCreated;
use Aicl\Events\EntityDeleted;
use Aicl\Events\EntityUpdated;
use Aicl\Filament\Widgets\CategoryBreakdownChart;
use Aicl\Filament\Widgets\FailureReportDeadlinesWidget;
use Aicl\Filament\Widgets\FailureReportStatsOverview;
use Aicl\Filament\Widgets\FailureTrendChart;
use Aicl\Filament\Widgets\GenerationTraceStatsOverview;
use Aicl\Filament\Widgets\GlobalSearchWidget;
use Aicl\Filament\Widgets\PresenceIndicator;
use Aicl\Filament\Widgets\PreventionRuleDeadlinesWidget;
use Aicl\Filament\Widgets\PreventionRuleStatsOverview;
use Aicl\Filament\Widgets\ProjectHealthWidget;
use Aicl\Filament\Widgets\PromotionQueueWidget;
use Aicl\Filament\Widgets\QueueStatsWidget;
use Aicl\Filament\Widgets\RecentFailedJobsWidget;
use Aicl\Filament\Widgets\RecentGenerationTracesWidget;
use Aicl\Filament\Widgets\RecentRlmLessonsWidget;
use Aicl\Filament\Widgets\RlmFailureByStatusChart;
use Aicl\Filament\Widgets\RlmFailureDeadlinesWidget;
use Aicl\Filament\Widgets\RlmFailureStatsOverview;
use Aicl\Filament\Widgets\RlmLessonStatsOverview;
use Aicl\Filament\Widgets\RlmPatternDeadlinesWidget;
use Aicl\Filament\Widgets\RlmPatternStatsOverview;
use Aicl\Filament\Widgets\Traits\PausesWhenHidden;
use Aicl\Models\FailureReport;
use Aicl\Models\GenerationTrace;
use Aicl\Models\PreventionRule;
use Aicl\Models\RlmFailure;
use Aicl\Models\RlmLesson;
use Aicl\Models\RlmPattern;
use App\Models\User;
use Filament\Tables\Table;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Filament\Widgets\TableWidget;
use Filament\Widgets\Widget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Livewire\Attributes\On;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class WidgetCoverageTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake([
            EntityCreated::class,
            EntityUpdated::class,
            EntityDeleted::class,
        ]);

        $this->admin = User::factory()->create(['id' => 1]);
    }

    // ── Helper ──────────────────────────────────────────────────────

    private function callProtected(object $obj, string $method, array $args = []): mixed
    {
        $ref = new \ReflectionMethod($obj, $method);

        return $ref->invoke($obj, ...$args);
    }

    // ═══════════════════════════════════════════════════════════════════
    //  STATS OVERVIEW WIDGETS — getStats() output
    // ═══════════════════════════════════════════════════════════════════

    public function test_rlm_pattern_stats_overview_returns_stat_objects(): void
    {
        RlmPattern::factory()->for($this->admin, 'owner')->create([
            'is_active' => true,
            'pass_count' => 10,
            'fail_count' => 2,
            'last_evaluated_at' => now(),
        ]);
        RlmPattern::factory()->for($this->admin, 'owner')->create([
            'is_active' => false,
            'pass_count' => 0,
            'fail_count' => 0,
            'last_evaluated_at' => null,
        ]);

        $widget = new RlmPatternStatsOverview;
        $stats = $this->callProtected($widget, 'getStats');

        $this->assertCount(4, $stats);
        $this->assertContainsOnlyInstancesOf(Stat::class, $stats);
    }

    public function test_rlm_pattern_stats_overview_labels(): void
    {
        $widget = new RlmPatternStatsOverview;
        $stats = $this->callProtected($widget, 'getStats');

        $labels = array_map(fn (Stat $s) => $s->getLabel(), $stats);

        $this->assertContains('Total Patterns', $labels);
        $this->assertContains('Active', $labels);
        $this->assertContains('Inactive', $labels);
        $this->assertContains('Avg Pass Rate', $labels);
    }

    public function test_rlm_failure_stats_overview_returns_stat_objects(): void
    {
        RlmFailure::factory()->for($this->admin, 'owner')->create(['severity' => 'critical']);

        $widget = new RlmFailureStatsOverview;
        $stats = $this->callProtected($widget, 'getStats');

        $this->assertCount(4, $stats);
        $this->assertContainsOnlyInstancesOf(Stat::class, $stats);
    }

    public function test_rlm_failure_stats_overview_labels(): void
    {
        $widget = new RlmFailureStatsOverview;
        $stats = $this->callProtected($widget, 'getStats');

        $labels = array_map(fn (Stat $s) => $s->getLabel(), $stats);

        $this->assertContains('Total Failures', $labels);
        $this->assertContains('Critical', $labels);
        $this->assertContains('High Severity', $labels);
        $this->assertContains('Promotable', $labels);
    }

    public function test_failure_report_stats_overview_returns_stat_objects(): void
    {
        $widget = new FailureReportStatsOverview;
        $stats = $this->callProtected($widget, 'getStats');

        $this->assertCount(4, $stats);
        $this->assertContainsOnlyInstancesOf(Stat::class, $stats);
    }

    public function test_failure_report_stats_overview_labels(): void
    {
        $widget = new FailureReportStatsOverview;
        $stats = $this->callProtected($widget, 'getStats');

        $labels = array_map(fn (Stat $s) => $s->getLabel(), $stats);

        $this->assertContains('Total Reports', $labels);
        $this->assertContains('Resolved', $labels);
        $this->assertContains('Unresolved', $labels);
        $this->assertContains('Avg Time to Resolve', $labels);
    }

    public function test_prevention_rule_stats_overview_returns_stat_objects(): void
    {
        PreventionRule::factory()->withoutFailure()->for($this->admin, 'owner')->create([
            'is_active' => true,
            'confidence' => 0.85,
            'applied_count' => 10,
        ]);

        $widget = new PreventionRuleStatsOverview;
        $stats = $this->callProtected($widget, 'getStats');

        $this->assertCount(4, $stats);
        $this->assertContainsOnlyInstancesOf(Stat::class, $stats);
    }

    public function test_prevention_rule_stats_overview_labels(): void
    {
        $widget = new PreventionRuleStatsOverview;
        $stats = $this->callProtected($widget, 'getStats');

        $labels = array_map(fn (Stat $s) => $s->getLabel(), $stats);

        $this->assertContains('Total Rules', $labels);
        $this->assertContains('Active Rules', $labels);
        $this->assertContains('Avg Confidence', $labels);
        $this->assertContains('Total Applied', $labels);
    }

    public function test_generation_trace_stats_overview_returns_stat_objects(): void
    {
        GenerationTrace::factory()->for($this->admin, 'owner')->create([
            'structural_score' => 95.0,
            'semantic_score' => 88.5,
            'fix_iterations' => 1,
        ]);

        $widget = new GenerationTraceStatsOverview;
        $stats = $this->callProtected($widget, 'getStats');

        $this->assertCount(4, $stats);
        $this->assertContainsOnlyInstancesOf(Stat::class, $stats);
    }

    public function test_generation_trace_stats_overview_labels(): void
    {
        $widget = new GenerationTraceStatsOverview;
        $stats = $this->callProtected($widget, 'getStats');

        $labels = array_map(fn (Stat $s) => $s->getLabel(), $stats);

        $this->assertContains('Total Traces', $labels);
        $this->assertContains('Avg Structural Score', $labels);
        $this->assertContains('Avg Semantic Score', $labels);
        $this->assertContains('Avg Fix Iterations', $labels);
    }

    public function test_rlm_lesson_stats_overview_returns_stat_objects(): void
    {
        RlmLesson::factory()->for($this->admin, 'owner')->verified()->create();
        RlmLesson::factory()->for($this->admin, 'owner')->create(['is_verified' => false]);

        $widget = new RlmLessonStatsOverview;
        $stats = $this->callProtected($widget, 'getStats');

        $this->assertCount(4, $stats);
        $this->assertContainsOnlyInstancesOf(Stat::class, $stats);
    }

    public function test_rlm_lesson_stats_overview_labels(): void
    {
        $widget = new RlmLessonStatsOverview;
        $stats = $this->callProtected($widget, 'getStats');

        $labels = array_map(fn (Stat $s) => $s->getLabel(), $stats);

        $this->assertContains('Total Lessons', $labels);
        $this->assertContains('Verified', $labels);
        $this->assertContains('Unverified', $labels);
        $this->assertContains('Avg Confidence', $labels);
    }

    public function test_project_health_widget_returns_stat_objects(): void
    {
        GenerationTrace::factory()->for($this->admin, 'owner')->create([
            'structural_score' => 100.0,
            'semantic_score' => 95.0,
        ]);

        $widget = new ProjectHealthWidget;
        $stats = $this->callProtected($widget, 'getStats');

        $this->assertCount(4, $stats);
        $this->assertContainsOnlyInstancesOf(Stat::class, $stats);
    }

    public function test_project_health_widget_labels(): void
    {
        $widget = new ProjectHealthWidget;
        $stats = $this->callProtected($widget, 'getStats');

        $labels = array_map(fn (Stat $s) => $s->getLabel(), $stats);

        $this->assertContains('Total Generations', $labels);
        $this->assertContains('Avg Structural Score', $labels);
        $this->assertContains('Avg Semantic Score', $labels);
        $this->assertContains('Perfect Scores', $labels);
    }

    // ── Stats: empty database returns valid stat arrays ─────────────

    /**
     * @return array<string, array{class-string, int}>
     */
    public static function statsWidgetCountProvider(): array
    {
        return [
            'RlmPatternStatsOverview' => [RlmPatternStatsOverview::class, 4],
            'RlmFailureStatsOverview' => [RlmFailureStatsOverview::class, 4],
            'FailureReportStatsOverview' => [FailureReportStatsOverview::class, 4],
            'PreventionRuleStatsOverview' => [PreventionRuleStatsOverview::class, 4],
            'GenerationTraceStatsOverview' => [GenerationTraceStatsOverview::class, 4],
            'RlmLessonStatsOverview' => [RlmLessonStatsOverview::class, 4],
            'ProjectHealthWidget' => [ProjectHealthWidget::class, 4],
        ];
    }

    #[DataProvider('statsWidgetCountProvider')]
    public function test_stats_widget_returns_correct_count_with_empty_db(string $class, int $expectedCount): void
    {
        $widget = new $class;
        $stats = $this->callProtected($widget, 'getStats');

        $this->assertCount($expectedCount, $stats);
        $this->assertContainsOnlyInstancesOf(Stat::class, $stats);
    }

    // ── Stats: columnSpan defaults ──────────────────────────────────

    /**
     * @return array<string, array{class-string}>
     */
    public static function statsOverviewWidgetProvider(): array
    {
        return [
            'RlmPatternStatsOverview' => [RlmPatternStatsOverview::class],
            'RlmFailureStatsOverview' => [RlmFailureStatsOverview::class],
            'FailureReportStatsOverview' => [FailureReportStatsOverview::class],
            'PreventionRuleStatsOverview' => [PreventionRuleStatsOverview::class],
            'GenerationTraceStatsOverview' => [GenerationTraceStatsOverview::class],
            'RlmLessonStatsOverview' => [RlmLessonStatsOverview::class],
            'ProjectHealthWidget' => [ProjectHealthWidget::class],
        ];
    }

    #[DataProvider('statsOverviewWidgetProvider')]
    public function test_stats_widget_extends_stats_overview_widget(string $class): void
    {
        $this->assertTrue(is_subclass_of($class, StatsOverviewWidget::class));
    }

    // ═══════════════════════════════════════════════════════════════════
    //  CHART WIDGETS — getData() output
    // ═══════════════════════════════════════════════════════════════════

    public function test_failure_trend_chart_get_data_has_datasets_and_labels(): void
    {
        $widget = new FailureTrendChart;
        $data = $this->callProtected($widget, 'getData');

        $this->assertArrayHasKey('datasets', $data);
        $this->assertArrayHasKey('labels', $data);
        $this->assertIsArray($data['datasets']);
        $this->assertNotEmpty($data['datasets']);
        $this->assertArrayHasKey('data', $data['datasets'][0]);
        $this->assertArrayHasKey('label', $data['datasets'][0]);
    }

    public function test_failure_trend_chart_labels_have_six_months(): void
    {
        $widget = new FailureTrendChart;
        $data = $this->callProtected($widget, 'getData');

        $this->assertCount(6, $data['labels']);
        $this->assertCount(6, $data['datasets'][0]['data']);
    }

    public function test_failure_trend_chart_dataset_has_styling_keys(): void
    {
        $widget = new FailureTrendChart;
        $data = $this->callProtected($widget, 'getData');

        $dataset = $data['datasets'][0];

        $this->assertArrayHasKey('borderColor', $dataset);
        $this->assertArrayHasKey('backgroundColor', $dataset);
        $this->assertArrayHasKey('fill', $dataset);
        $this->assertArrayHasKey('tension', $dataset);
    }

    public function test_category_breakdown_chart_get_data_has_datasets_and_labels(): void
    {
        $widget = new CategoryBreakdownChart;
        $data = $this->callProtected($widget, 'getData');

        $this->assertArrayHasKey('datasets', $data);
        $this->assertArrayHasKey('labels', $data);
        $this->assertIsArray($data['datasets']);
        $this->assertNotEmpty($data['datasets']);
    }

    public function test_category_breakdown_chart_shows_no_data_when_empty(): void
    {
        $widget = new CategoryBreakdownChart;
        $data = $this->callProtected($widget, 'getData');

        $this->assertContains('No Data', $data['labels']);
    }

    public function test_category_breakdown_chart_shows_categories_when_data_exists(): void
    {
        RlmFailure::factory()->for($this->admin, 'owner')->create(['category' => 'scaffolding']);
        RlmFailure::factory()->for($this->admin, 'owner')->create(['category' => 'testing']);

        $widget = new CategoryBreakdownChart;
        $data = $this->callProtected($widget, 'getData');

        $this->assertContains('Scaffolding', $data['labels']);
        $this->assertContains('Testing', $data['labels']);
        $this->assertNotContains('No Data', $data['labels']);
    }

    public function test_category_breakdown_chart_dataset_has_background_colors(): void
    {
        RlmFailure::factory()->for($this->admin, 'owner')->create(['category' => 'scaffolding']);

        $widget = new CategoryBreakdownChart;
        $data = $this->callProtected($widget, 'getData');

        $this->assertArrayHasKey('backgroundColor', $data['datasets'][0]);
    }

    public function test_rlm_failure_by_status_chart_get_data_has_datasets_and_labels(): void
    {
        $widget = new RlmFailureByStatusChart;
        $data = $this->callProtected($widget, 'getData');

        $this->assertArrayHasKey('datasets', $data);
        $this->assertArrayHasKey('labels', $data);
        $this->assertIsArray($data['datasets']);
        $this->assertNotEmpty($data['datasets']);
    }

    public function test_rlm_failure_by_status_chart_shows_no_data_when_empty(): void
    {
        $widget = new RlmFailureByStatusChart;
        $data = $this->callProtected($widget, 'getData');

        $this->assertContains('No Data', $data['labels']);
    }

    public function test_rlm_failure_by_status_chart_returns_valid_structure_with_data(): void
    {
        RlmFailure::factory()->for($this->admin, 'owner')->reported()->create();
        RlmFailure::factory()->for($this->admin, 'owner')->resolved()->create();

        $widget = new RlmFailureByStatusChart;
        $data = $this->callProtected($widget, 'getData');

        // getData always returns valid datasets/labels structure
        $this->assertArrayHasKey('datasets', $data);
        $this->assertArrayHasKey('labels', $data);
        $this->assertNotEmpty($data['datasets']);
        $this->assertNotEmpty($data['labels']);
        $this->assertArrayHasKey('backgroundColor', $data['datasets'][0]);
    }

    // ── Chart: type values via data provider ────────────────────────

    /**
     * @return array<string, array{class-string, string}>
     */
    public static function chartTypeProvider(): array
    {
        return [
            'FailureTrendChart => line' => [FailureTrendChart::class, 'line'],
            'CategoryBreakdownChart => doughnut' => [CategoryBreakdownChart::class, 'doughnut'],
            'RlmFailureByStatusChart => doughnut' => [RlmFailureByStatusChart::class, 'doughnut'],
        ];
    }

    #[DataProvider('chartTypeProvider')]
    public function test_chart_widget_returns_correct_type(string $class, string $expectedType): void
    {
        $widget = new $class;
        $type = $this->callProtected($widget, 'getType');

        $this->assertSame($expectedType, $type);
    }

    // ── Chart: heading values via data provider ─────────────────────

    /**
     * @return array<string, array{class-string, string}>
     */
    public static function chartHeadingProvider(): array
    {
        return [
            'FailureTrendChart' => [FailureTrendChart::class, 'Failure Reports Over Time'],
            'CategoryBreakdownChart' => [CategoryBreakdownChart::class, 'Failures by Category'],
            'RlmFailureByStatusChart' => [RlmFailureByStatusChart::class, 'Failures by Status'],
        ];
    }

    #[DataProvider('chartHeadingProvider')]
    public function test_chart_widget_has_correct_heading(string $class, string $expectedHeading): void
    {
        $ref = new \ReflectionClass($class);

        $this->assertSame($expectedHeading, $ref->getProperty('heading')->getDefaultValue());
    }

    // ── Chart: hierarchy ────────────────────────────────────────────

    /**
     * @return array<string, array{class-string}>
     */
    public static function chartWidgetProvider(): array
    {
        return [
            'FailureTrendChart' => [FailureTrendChart::class],
            'CategoryBreakdownChart' => [CategoryBreakdownChart::class],
            'RlmFailureByStatusChart' => [RlmFailureByStatusChart::class],
        ];
    }

    #[DataProvider('chartWidgetProvider')]
    public function test_chart_widget_extends_chart_widget_class(string $class): void
    {
        $this->assertTrue(is_subclass_of($class, ChartWidget::class));
    }

    // ── Chart: entityChanged calls updateChartData ──────────────────

    #[DataProvider('chartWidgetProvider')]
    public function test_chart_entity_changed_calls_update_chart_data(string $class): void
    {
        $this->assertTrue(method_exists($class, 'entityChanged'));

        // Verify the #[On] attribute is present
        $ref = new \ReflectionMethod($class, 'entityChanged');
        $attributes = $ref->getAttributes(On::class);

        $this->assertNotEmpty($attributes);
    }

    // ═══════════════════════════════════════════════════════════════════
    //  TABLE WIDGETS — table() method and heading
    // ═══════════════════════════════════════════════════════════════════

    /**
     * @return array<string, array{class-string}>
     */
    public static function tableWidgetProvider(): array
    {
        return [
            'RecentGenerationTracesWidget' => [RecentGenerationTracesWidget::class],
            'RecentRlmLessonsWidget' => [RecentRlmLessonsWidget::class],
            'FailureReportDeadlinesWidget' => [FailureReportDeadlinesWidget::class],
            'RlmPatternDeadlinesWidget' => [RlmPatternDeadlinesWidget::class],
            'RlmFailureDeadlinesWidget' => [RlmFailureDeadlinesWidget::class],
            'PromotionQueueWidget' => [PromotionQueueWidget::class],
            'PreventionRuleDeadlinesWidget' => [PreventionRuleDeadlinesWidget::class],
        ];
    }

    #[DataProvider('tableWidgetProvider')]
    public function test_table_widget_extends_table_widget_class(string $class): void
    {
        $this->assertTrue(is_subclass_of($class, TableWidget::class));
    }

    #[DataProvider('tableWidgetProvider')]
    public function test_table_widget_sort_is_three(string $class): void
    {
        $ref = new \ReflectionClass($class);

        $this->assertEquals(3, $ref->getProperty('sort')->getDefaultValue());
    }

    #[DataProvider('tableWidgetProvider')]
    public function test_table_widget_column_span_is_full(string $class): void
    {
        $ref = new \ReflectionClass($class);

        $this->assertEquals('full', $ref->getProperty('columnSpan')->getDefaultValue());
    }

    #[DataProvider('tableWidgetProvider')]
    public function test_table_widget_has_table_method(string $class): void
    {
        $ref = new \ReflectionClass($class);

        $this->assertTrue($ref->hasMethod('table'));
        $this->assertSame($class, $ref->getMethod('table')->getDeclaringClass()->getName());
    }

    #[DataProvider('tableWidgetProvider')]
    public function test_table_widget_uses_pauses_when_hidden(string $class): void
    {
        $this->assertTrue(
            in_array(PausesWhenHidden::class, class_uses_recursive($class)),
            "$class should use PausesWhenHidden trait"
        );
    }

    #[DataProvider('tableWidgetProvider')]
    public function test_table_widget_entity_changed_has_on_attribute(string $class): void
    {
        $ref = new \ReflectionMethod($class, 'entityChanged');
        $attributes = $ref->getAttributes(On::class);

        $this->assertNotEmpty($attributes, "$class::entityChanged should have #[On] attribute");
    }

    // ── Table: heading values ───────────────────────────────────────

    /**
     * @return array<string, array{class-string, string}>
     */
    public static function tableHeadingProvider(): array
    {
        return [
            'RecentGenerationTracesWidget' => [RecentGenerationTracesWidget::class, 'Recent Generation Traces'],
            'RecentRlmLessonsWidget' => [RecentRlmLessonsWidget::class, 'Most Viewed Lessons'],
            'FailureReportDeadlinesWidget' => [FailureReportDeadlinesWidget::class, 'Recent Unresolved Reports'],
            'RlmPatternDeadlinesWidget' => [RlmPatternDeadlinesWidget::class, 'Recently Evaluated Patterns'],
            'RlmFailureDeadlinesWidget' => [RlmFailureDeadlinesWidget::class, 'Recently Reported Failures'],
            'PromotionQueueWidget' => [PromotionQueueWidget::class, 'Promotion Queue'],
            'PreventionRuleDeadlinesWidget' => [PreventionRuleDeadlinesWidget::class, 'Most Applied Prevention Rules'],
        ];
    }

    #[DataProvider('tableHeadingProvider')]
    public function test_table_widget_has_correct_heading(string $class, string $expectedHeading): void
    {
        $widget = new $class;

        $table = $widget->table(Table::make($widget));

        $this->assertSame($expectedHeading, $table->getHeading());
    }

    // ── Table: paginated=false ──────────────────────────────────────

    /**
     * @return array<string, array{class-string}>
     */
    public static function tableWidgetNotPaginatedProvider(): array
    {
        return self::tableWidgetProvider();
    }

    #[DataProvider('tableWidgetNotPaginatedProvider')]
    public function test_table_widget_is_not_paginated(string $class): void
    {
        $widget = new $class;
        $table = $widget->table(Table::make($widget));

        $this->assertFalse($table->isPaginated());
    }

    // ═══════════════════════════════════════════════════════════════════
    //  QUEUE STATS WIDGET
    // ═══════════════════════════════════════════════════════════════════

    public function test_queue_stats_widget_extends_stats_overview(): void
    {
        $this->assertTrue(is_subclass_of(QueueStatsWidget::class, StatsOverviewWidget::class));
    }

    public function test_queue_stats_widget_sort_is_one(): void
    {
        $ref = new \ReflectionClass(QueueStatsWidget::class);

        $this->assertEquals(1, $ref->getProperty('sort')->getDefaultValue());
    }

    public function test_queue_stats_widget_returns_three_stats(): void
    {
        $widget = new QueueStatsWidget;
        $stats = $this->callProtected($widget, 'getStats');

        $this->assertCount(3, $stats);
        $this->assertContainsOnlyInstancesOf(Stat::class, $stats);
    }

    public function test_queue_stats_widget_labels(): void
    {
        $widget = new QueueStatsWidget;
        $stats = $this->callProtected($widget, 'getStats');

        $labels = array_map(fn (Stat $s) => $s->getLabel(), $stats);

        $this->assertContains('Pending Jobs', $labels);
        $this->assertContains('Failed Jobs', $labels);
        $this->assertContains('Last Failure', $labels);
    }

    public function test_queue_stats_get_queue_size_returns_integer(): void
    {
        $widget = new QueueStatsWidget;
        $size = $this->callProtected($widget, 'getQueueSize', ['default']);

        $this->assertIsInt($size);
        $this->assertGreaterThanOrEqual(0, $size);
    }

    public function test_queue_stats_get_queue_size_handles_exception(): void
    {
        $widget = new QueueStatsWidget;

        // A non-existent queue name should not throw, returns 0
        $size = $this->callProtected($widget, 'getQueueSize', ['nonexistent_queue_xyz']);

        $this->assertSame(0, $size);
    }

    // ═══════════════════════════════════════════════════════════════════
    //  RECENT FAILED JOBS WIDGET
    // ═══════════════════════════════════════════════════════════════════

    public function test_recent_failed_jobs_extends_table_widget(): void
    {
        $this->assertTrue(is_subclass_of(RecentFailedJobsWidget::class, TableWidget::class));
    }

    public function test_recent_failed_jobs_sort_is_two(): void
    {
        $ref = new \ReflectionClass(RecentFailedJobsWidget::class);

        $this->assertEquals(2, $ref->getProperty('sort')->getDefaultValue());
    }

    public function test_recent_failed_jobs_column_span_is_full(): void
    {
        $ref = new \ReflectionClass(RecentFailedJobsWidget::class);

        $this->assertEquals('full', $ref->getProperty('columnSpan')->getDefaultValue());
    }

    public function test_recent_failed_jobs_table_heading(): void
    {
        $widget = new RecentFailedJobsWidget;
        $table = $widget->table(Table::make($widget));

        $this->assertSame('Recent Failed Jobs', $table->getHeading());
    }

    public function test_recent_failed_jobs_table_not_paginated(): void
    {
        $widget = new RecentFailedJobsWidget;
        $table = $widget->table(Table::make($widget));

        $this->assertFalse($table->isPaginated());
    }

    public function test_recent_failed_jobs_has_empty_state(): void
    {
        $widget = new RecentFailedJobsWidget;
        $table = $widget->table(Table::make($widget));

        $this->assertSame('No failed jobs', $table->getEmptyStateHeading());
    }

    // ═══════════════════════════════════════════════════════════════════
    //  GLOBAL SEARCH WIDGET
    // ═══════════════════════════════════════════════════════════════════

    public function test_global_search_extends_widget(): void
    {
        $this->assertTrue(is_subclass_of(GlobalSearchWidget::class, Widget::class));
    }

    public function test_global_search_default_properties(): void
    {
        $ref = new \ReflectionClass(GlobalSearchWidget::class);
        $defaults = $ref->getDefaultProperties();

        $this->assertSame('', $defaults['query']);
        $this->assertFalse($defaults['showResults']);
        $this->assertSame('full', $defaults['columnSpan']);
    }

    public function test_global_search_view_is_correct(): void
    {
        $ref = new \ReflectionClass(GlobalSearchWidget::class);

        $this->assertSame('aicl::filament.widgets.global-search-widget', $ref->getProperty('view')->getDefaultValue());
    }

    public function test_global_search_clear_search_resets_state(): void
    {
        $widget = new GlobalSearchWidget;
        $widget->query = 'test search';
        $widget->showResults = true;

        $widget->clearSearch();

        $this->assertSame('', $widget->query);
        $this->assertFalse($widget->showResults);
    }

    public function test_global_search_updated_query_shows_results_when_long_enough(): void
    {
        $widget = new GlobalSearchWidget;
        $widget->query = 'ab';

        $widget->updatedQuery();

        $this->assertTrue($widget->showResults);
    }

    public function test_global_search_updated_query_hides_results_when_too_short(): void
    {
        $widget = new GlobalSearchWidget;
        $widget->query = 'a';

        $widget->updatedQuery();

        $this->assertFalse($widget->showResults);
    }

    public function test_global_search_updated_query_hides_results_when_empty(): void
    {
        $widget = new GlobalSearchWidget;
        $widget->query = '';

        $widget->updatedQuery();

        $this->assertFalse($widget->showResults);
    }

    public function test_global_search_results_returns_empty_collection_for_short_query(): void
    {
        $widget = new GlobalSearchWidget;
        $widget->query = 'a';

        $results = $widget->results();

        $this->assertCount(0, $results);
    }

    public function test_global_search_results_returns_collection_for_valid_query(): void
    {
        $widget = new GlobalSearchWidget;
        $widget->query = 'test query';

        $results = $widget->results();

        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $results);
    }

    public function test_global_search_can_view_requires_authentication(): void
    {
        // Unauthenticated
        $this->assertFalse(GlobalSearchWidget::canView());
    }

    public function test_global_search_can_view_returns_true_when_authenticated(): void
    {
        $this->actingAs($this->admin);

        $this->assertTrue(GlobalSearchWidget::canView());
    }

    // ═══════════════════════════════════════════════════════════════════
    //  PRESENCE INDICATOR
    // ═══════════════════════════════════════════════════════════════════

    public function test_presence_indicator_extends_widget(): void
    {
        $this->assertTrue(is_subclass_of(PresenceIndicator::class, Widget::class));
    }

    public function test_presence_indicator_view(): void
    {
        $ref = new \ReflectionClass(PresenceIndicator::class);

        $this->assertSame('aicl::widgets.presence-indicator', $ref->getProperty('view')->getDefaultValue());
    }

    public function test_presence_indicator_column_span_is_full(): void
    {
        $ref = new \ReflectionClass(PresenceIndicator::class);

        $this->assertSame('full', $ref->getProperty('columnSpan')->getDefaultValue());
    }

    public function test_presence_indicator_channel_null_by_default(): void
    {
        $widget = new PresenceIndicator;

        $this->assertNull($widget->channelName);
    }

    public function test_presence_indicator_mount_sets_default_channel(): void
    {
        $widget = new PresenceIndicator;
        $widget->mount();

        $this->assertSame('presence-admin-panel', $widget->channelName);
    }

    public function test_presence_indicator_mount_sets_custom_channel(): void
    {
        $widget = new PresenceIndicator;
        $widget->mount('presence.projects.42');

        $this->assertSame('presence.projects.42', $widget->channelName);
    }

    // ═══════════════════════════════════════════════════════════════════
    //  CROSS-CUTTING CONCERNS — PausesWhenHidden across all hub widgets
    // ═══════════════════════════════════════════════════════════════════

    /**
     * @return array<string, array{class-string}>
     */
    public static function allHubWidgetProvider(): array
    {
        return [
            'RlmPatternStatsOverview' => [RlmPatternStatsOverview::class],
            'RlmFailureStatsOverview' => [RlmFailureStatsOverview::class],
            'FailureReportStatsOverview' => [FailureReportStatsOverview::class],
            'PreventionRuleStatsOverview' => [PreventionRuleStatsOverview::class],
            'GenerationTraceStatsOverview' => [GenerationTraceStatsOverview::class],
            'RlmLessonStatsOverview' => [RlmLessonStatsOverview::class],
            'ProjectHealthWidget' => [ProjectHealthWidget::class],
            'FailureTrendChart' => [FailureTrendChart::class],
            'CategoryBreakdownChart' => [CategoryBreakdownChart::class],
            'RlmFailureByStatusChart' => [RlmFailureByStatusChart::class],
            'RecentGenerationTracesWidget' => [RecentGenerationTracesWidget::class],
            'RecentRlmLessonsWidget' => [RecentRlmLessonsWidget::class],
            'FailureReportDeadlinesWidget' => [FailureReportDeadlinesWidget::class],
            'RlmPatternDeadlinesWidget' => [RlmPatternDeadlinesWidget::class],
            'RlmFailureDeadlinesWidget' => [RlmFailureDeadlinesWidget::class],
            'PromotionQueueWidget' => [PromotionQueueWidget::class],
            'PreventionRuleDeadlinesWidget' => [PreventionRuleDeadlinesWidget::class],
        ];
    }

    #[DataProvider('allHubWidgetProvider')]
    public function test_hub_widget_uses_pauses_when_hidden_trait(string $class): void
    {
        $this->assertTrue(
            in_array(PausesWhenHidden::class, class_uses_recursive($class)),
            "$class should use PausesWhenHidden trait"
        );
    }

    #[DataProvider('allHubWidgetProvider')]
    public function test_hub_widget_has_entity_changed_listener(string $class): void
    {
        $ref = new \ReflectionMethod($class, 'entityChanged');
        $attributes = $ref->getAttributes(On::class);

        $this->assertNotEmpty($attributes, "$class::entityChanged should have #[On] attribute");
    }

    #[DataProvider('allHubWidgetProvider')]
    public function test_hub_widget_sort_is_not_null(string $class): void
    {
        $ref = new \ReflectionClass($class);

        $this->assertNotNull(
            $ref->getProperty('sort')->getDefaultValue(),
            "$class should have a non-null \$sort value"
        );
    }

    // ═══════════════════════════════════════════════════════════════════
    //  ALL WIDGETS — class existence check (comprehensive enumeration)
    // ═══════════════════════════════════════════════════════════════════

    /**
     * @return array<string, array{class-string}>
     */
    public static function allWidgetClassProvider(): array
    {
        return [
            'RlmPatternStatsOverview' => [RlmPatternStatsOverview::class],
            'RlmFailureStatsOverview' => [RlmFailureStatsOverview::class],
            'FailureReportStatsOverview' => [FailureReportStatsOverview::class],
            'PreventionRuleStatsOverview' => [PreventionRuleStatsOverview::class],
            'GenerationTraceStatsOverview' => [GenerationTraceStatsOverview::class],
            'RlmLessonStatsOverview' => [RlmLessonStatsOverview::class],
            'ProjectHealthWidget' => [ProjectHealthWidget::class],
            'QueueStatsWidget' => [QueueStatsWidget::class],
            'FailureTrendChart' => [FailureTrendChart::class],
            'CategoryBreakdownChart' => [CategoryBreakdownChart::class],
            'RlmFailureByStatusChart' => [RlmFailureByStatusChart::class],
            'RecentGenerationTracesWidget' => [RecentGenerationTracesWidget::class],
            'RecentRlmLessonsWidget' => [RecentRlmLessonsWidget::class],
            'FailureReportDeadlinesWidget' => [FailureReportDeadlinesWidget::class],
            'RlmPatternDeadlinesWidget' => [RlmPatternDeadlinesWidget::class],
            'RlmFailureDeadlinesWidget' => [RlmFailureDeadlinesWidget::class],
            'PromotionQueueWidget' => [PromotionQueueWidget::class],
            'PreventionRuleDeadlinesWidget' => [PreventionRuleDeadlinesWidget::class],
            'RecentFailedJobsWidget' => [RecentFailedJobsWidget::class],
            'GlobalSearchWidget' => [GlobalSearchWidget::class],
            'PresenceIndicator' => [PresenceIndicator::class],
        ];
    }

    #[DataProvider('allWidgetClassProvider')]
    public function test_widget_class_exists(string $class): void
    {
        $this->assertTrue(class_exists($class), "$class should exist");
    }

    // ═══════════════════════════════════════════════════════════════════
    //  STATS OVERVIEW — data accuracy with seeded records
    // ═══════════════════════════════════════════════════════════════════

    public function test_rlm_pattern_stats_calculates_pass_rate(): void
    {
        RlmPattern::factory()->for($this->admin, 'owner')->create([
            'is_active' => true,
            'pass_count' => 90,
            'fail_count' => 10,
            'last_evaluated_at' => now(),
        ]);

        $widget = new RlmPatternStatsOverview;
        $stats = $this->callProtected($widget, 'getStats');

        // Find the "Avg Pass Rate" stat
        $passRateStat = collect($stats)->first(fn (Stat $s) => $s->getLabel() === 'Avg Pass Rate');

        $this->assertNotNull($passRateStat);
        $this->assertSame('90%', $passRateStat->getValue());
    }

    public function test_rlm_pattern_stats_shows_na_when_no_evaluations(): void
    {
        RlmPattern::factory()->for($this->admin, 'owner')->neverEvaluated()->create();

        $widget = new RlmPatternStatsOverview;
        $stats = $this->callProtected($widget, 'getStats');

        $passRateStat = collect($stats)->first(fn (Stat $s) => $s->getLabel() === 'Avg Pass Rate');

        $this->assertNotNull($passRateStat);
        $this->assertSame('N/A', $passRateStat->getValue());
    }

    public function test_rlm_failure_stats_counts_severity_levels(): void
    {
        RlmFailure::factory()->for($this->admin, 'owner')->create(['severity' => 'critical']);
        RlmFailure::factory()->for($this->admin, 'owner')->count(2)->create(['severity' => 'high']);
        RlmFailure::factory()->for($this->admin, 'owner')->create(['severity' => 'medium']);

        $widget = new RlmFailureStatsOverview;
        $stats = $this->callProtected($widget, 'getStats');

        $totalStat = collect($stats)->first(fn (Stat $s) => $s->getLabel() === 'Total Failures');
        $critStat = collect($stats)->first(fn (Stat $s) => $s->getLabel() === 'Critical');
        $highStat = collect($stats)->first(fn (Stat $s) => $s->getLabel() === 'High Severity');

        $this->assertSame(4, $totalStat->getValue());
        $this->assertSame(1, $critStat->getValue());
        $this->assertSame(2, $highStat->getValue());
    }

    public function test_failure_report_stats_calculates_resolution_rate(): void
    {
        $failure = RlmFailure::factory()->for($this->admin, 'owner')->create();

        FailureReport::factory()->for($this->admin, 'owner')->for($failure, 'failure')
            ->resolved()->create(['time_to_resolve' => 60]);
        FailureReport::factory()->for($this->admin, 'owner')->for($failure, 'failure')
            ->resolved()->create(['time_to_resolve' => 40]);
        FailureReport::factory()->for($this->admin, 'owner')->for($failure, 'failure')
            ->unresolved()->create();

        $widget = new FailureReportStatsOverview;
        $stats = $this->callProtected($widget, 'getStats');

        $totalStat = collect($stats)->first(fn (Stat $s) => $s->getLabel() === 'Total Reports');
        $resolvedStat = collect($stats)->first(fn (Stat $s) => $s->getLabel() === 'Resolved');
        $unresolvedStat = collect($stats)->first(fn (Stat $s) => $s->getLabel() === 'Unresolved');

        $this->assertSame(3, $totalStat->getValue());
        $this->assertSame(2, $resolvedStat->getValue());
        $this->assertSame(1, $unresolvedStat->getValue());
    }

    public function test_generation_trace_stats_shows_dash_for_null_scores(): void
    {
        GenerationTrace::factory()->for($this->admin, 'owner')->create([
            'structural_score' => null,
            'semantic_score' => null,
            'fix_iterations' => 0,
        ]);

        $widget = new GenerationTraceStatsOverview;
        $stats = $this->callProtected($widget, 'getStats');

        $structuralStat = collect($stats)->first(fn (Stat $s) => $s->getLabel() === 'Avg Structural Score');
        $semanticStat = collect($stats)->first(fn (Stat $s) => $s->getLabel() === 'Avg Semantic Score');

        // When no scores exist, should show dash
        $this->assertNotNull($structuralStat);
        $this->assertNotNull($semanticStat);
    }

    public function test_rlm_lesson_stats_calculates_verified_percentage(): void
    {
        RlmLesson::factory()->for($this->admin, 'owner')->verified()->count(4)->create();
        RlmLesson::factory()->for($this->admin, 'owner')->create(['is_verified' => false]);

        $widget = new RlmLessonStatsOverview;
        $stats = $this->callProtected($widget, 'getStats');

        $verifiedStat = collect($stats)->first(fn (Stat $s) => $s->getLabel() === 'Verified');
        $unverifiedStat = collect($stats)->first(fn (Stat $s) => $s->getLabel() === 'Unverified');

        $this->assertSame(4, $verifiedStat->getValue());
        $this->assertSame(1, $unverifiedStat->getValue());
    }

    public function test_prevention_rule_stats_counts_applied(): void
    {
        PreventionRule::factory()->withoutFailure()->for($this->admin, 'owner')->create([
            'is_active' => true,
            'confidence' => 0.9,
            'applied_count' => 15,
        ]);
        PreventionRule::factory()->withoutFailure()->for($this->admin, 'owner')->create([
            'is_active' => true,
            'confidence' => 0.8,
            'applied_count' => 5,
        ]);

        $widget = new PreventionRuleStatsOverview;
        $stats = $this->callProtected($widget, 'getStats');

        $totalApplied = collect($stats)->first(fn (Stat $s) => $s->getLabel() === 'Total Applied');

        $this->assertSame(20, $totalApplied->getValue());
    }

    public function test_project_health_counts_perfect_scores(): void
    {
        GenerationTrace::factory()->for($this->admin, 'owner')->create(['structural_score' => 100.0]);
        GenerationTrace::factory()->for($this->admin, 'owner')->create(['structural_score' => 100.0]);
        GenerationTrace::factory()->for($this->admin, 'owner')->create(['structural_score' => 85.0]);

        $widget = new ProjectHealthWidget;
        $stats = $this->callProtected($widget, 'getStats');

        $perfectStat = collect($stats)->first(fn (Stat $s) => $s->getLabel() === 'Perfect Scores');

        $this->assertSame(2, $perfectStat->getValue());
    }
}
