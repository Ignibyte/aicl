<?php

namespace Aicl\Tests\Unit\Filament\Widgets;

use Aicl\Filament\Widgets\CategoryBreakdownChart;
use Aicl\Filament\Widgets\FailureReportDeadlinesWidget;
use Aicl\Filament\Widgets\FailureReportStatsOverview;
use Aicl\Filament\Widgets\FailureTrendChart;
use Aicl\Filament\Widgets\GenerationTraceStatsOverview;
use Aicl\Filament\Widgets\PreventionRuleDeadlinesWidget;
use Aicl\Filament\Widgets\PreventionRuleStatsOverview;
use Aicl\Filament\Widgets\ProjectHealthWidget;
use Aicl\Filament\Widgets\PromotionQueueWidget;
use Aicl\Filament\Widgets\RecentGenerationTracesWidget;
use Aicl\Filament\Widgets\RecentRlmLessonsWidget;
use Aicl\Filament\Widgets\RlmFailureByStatusChart;
use Aicl\Filament\Widgets\RlmFailureDeadlinesWidget;
use Aicl\Filament\Widgets\RlmFailureStatsOverview;
use Aicl\Filament\Widgets\RlmLessonStatsOverview;
use Aicl\Filament\Widgets\RlmPatternDeadlinesWidget;
use Aicl\Filament\Widgets\RlmPatternStatsOverview;
use Aicl\Filament\Widgets\Traits\PausesWhenHidden;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\TableWidget;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class WidgetPropertyTest extends TestCase
{
    // ── Helpers ───────────────────────────────────────────────────

    private function callProtectedMethod(object $obj, string $method, array $args = []): mixed
    {
        $ref = new \ReflectionMethod($obj, $method);

        return $ref->invoke($obj, ...$args);
    }

    // ── Stats Overview: class hierarchy ──────────────────────────

    #[DataProvider('statsOverviewWidgetProvider')]
    public function test_stats_widget_extends_stats_overview_widget(string $class): void
    {
        $this->assertTrue(is_subclass_of($class, StatsOverviewWidget::class));
    }

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

    // ── Stats Overview: sort values ──────────────────────────────

    #[DataProvider('statsOverviewSortOneProvider')]
    public function test_stats_widget_sort_is_one(string $class): void
    {
        $ref = new \ReflectionClass($class);
        $this->assertEquals(1, $ref->getProperty('sort')->getDefaultValue());
    }

    /**
     * @return array<string, array{class-string}>
     */
    public static function statsOverviewSortOneProvider(): array
    {
        return [
            'RlmPatternStatsOverview' => [RlmPatternStatsOverview::class],
            'RlmFailureStatsOverview' => [RlmFailureStatsOverview::class],
            'FailureReportStatsOverview' => [FailureReportStatsOverview::class],
            'PreventionRuleStatsOverview' => [PreventionRuleStatsOverview::class],
            'GenerationTraceStatsOverview' => [GenerationTraceStatsOverview::class],
            'RlmLessonStatsOverview' => [RlmLessonStatsOverview::class],
        ];
    }

    public function test_project_health_widget_sort_is_four(): void
    {
        $ref = new \ReflectionClass(ProjectHealthWidget::class);
        $this->assertEquals(4, $ref->getProperty('sort')->getDefaultValue());
    }

    // ── Stats Overview: PausesWhenHidden trait ────────────────────

    #[DataProvider('statsOverviewWidgetProvider')]
    public function test_stats_widget_uses_pauses_when_hidden(string $class): void
    {
        $this->assertTrue(
            in_array(PausesWhenHidden::class, class_uses_recursive($class))
        );
    }

    // ── Stats Overview: entityChanged listener ───────────────────

    #[DataProvider('statsOverviewWidgetProvider')]
    public function test_stats_widget_has_entity_changed_method(string $class): void
    {
        $this->assertTrue(method_exists($class, 'entityChanged'));
    }

    #[DataProvider('statsOverviewWidgetProvider')]
    public function test_stats_widget_entity_changed_has_on_attribute(string $class): void
    {
        $ref = new \ReflectionMethod($class, 'entityChanged');
        $attributes = $ref->getAttributes(\Livewire\Attributes\On::class);

        $this->assertNotEmpty($attributes, "$class::entityChanged should have the #[On] attribute");
    }

    // ── Chart Widgets: class hierarchy ───────────────────────────

    #[DataProvider('chartWidgetProvider')]
    public function test_chart_widget_extends_chart_widget(string $class): void
    {
        $this->assertTrue(is_subclass_of($class, ChartWidget::class));
    }

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

    // ── Chart Widgets: sort values ───────────────────────────────

    public function test_failure_trend_chart_sort(): void
    {
        $ref = new \ReflectionClass(FailureTrendChart::class);
        $this->assertEquals(1, $ref->getProperty('sort')->getDefaultValue());
    }

    public function test_category_breakdown_chart_sort(): void
    {
        $ref = new \ReflectionClass(CategoryBreakdownChart::class);
        $this->assertEquals(2, $ref->getProperty('sort')->getDefaultValue());
    }

    public function test_rlm_failure_by_status_chart_sort(): void
    {
        $ref = new \ReflectionClass(RlmFailureByStatusChart::class);
        $this->assertEquals(2, $ref->getProperty('sort')->getDefaultValue());
    }

    // ── Chart Widgets: heading ───────────────────────────────────

    public function test_failure_trend_chart_heading(): void
    {
        $ref = new \ReflectionClass(FailureTrendChart::class);
        $this->assertEquals('Failure Reports Over Time', $ref->getProperty('heading')->getDefaultValue());
    }

    public function test_category_breakdown_chart_heading(): void
    {
        $ref = new \ReflectionClass(CategoryBreakdownChart::class);
        $this->assertEquals('Failures by Category', $ref->getProperty('heading')->getDefaultValue());
    }

    public function test_rlm_failure_by_status_chart_heading(): void
    {
        $ref = new \ReflectionClass(RlmFailureByStatusChart::class);
        $this->assertEquals('Failures by Status', $ref->getProperty('heading')->getDefaultValue());
    }

    // ── Chart Widgets: getType() ─────────────────────────────────

    public function test_failure_trend_chart_type_is_line(): void
    {
        $widget = new FailureTrendChart;
        $this->assertEquals('line', $this->callProtectedMethod($widget, 'getType'));
    }

    public function test_category_breakdown_chart_type_is_doughnut(): void
    {
        $widget = new CategoryBreakdownChart;
        $this->assertEquals('doughnut', $this->callProtectedMethod($widget, 'getType'));
    }

    public function test_rlm_failure_by_status_chart_type_is_doughnut(): void
    {
        $widget = new RlmFailureByStatusChart;
        $this->assertEquals('doughnut', $this->callProtectedMethod($widget, 'getType'));
    }

    // ── Chart Widgets: PausesWhenHidden trait ─────────────────────

    #[DataProvider('chartWidgetProvider')]
    public function test_chart_widget_uses_pauses_when_hidden(string $class): void
    {
        $this->assertTrue(
            in_array(PausesWhenHidden::class, class_uses_recursive($class))
        );
    }

    // ── Chart Widgets: entityChanged listener ────────────────────

    #[DataProvider('chartWidgetProvider')]
    public function test_chart_widget_has_entity_changed_method(string $class): void
    {
        $this->assertTrue(method_exists($class, 'entityChanged'));
    }

    #[DataProvider('chartWidgetProvider')]
    public function test_chart_widget_entity_changed_has_on_attribute(string $class): void
    {
        $ref = new \ReflectionMethod($class, 'entityChanged');
        $attributes = $ref->getAttributes(\Livewire\Attributes\On::class);

        $this->assertNotEmpty($attributes, "$class::entityChanged should have the #[On] attribute");
    }

    // ── Table Widgets: class hierarchy ───────────────────────────

    #[DataProvider('tableWidgetProvider')]
    public function test_table_widget_extends_table_widget(string $class): void
    {
        $this->assertTrue(is_subclass_of($class, TableWidget::class));
    }

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

    // ── Table Widgets: sort = 3 ──────────────────────────────────

    #[DataProvider('tableWidgetProvider')]
    public function test_table_widget_sort_is_three(string $class): void
    {
        $ref = new \ReflectionClass($class);
        $this->assertEquals(3, $ref->getProperty('sort')->getDefaultValue());
    }

    // ── Table Widgets: columnSpan = 'full' ───────────────────────

    #[DataProvider('tableWidgetProvider')]
    public function test_table_widget_column_span_is_full(string $class): void
    {
        $ref = new \ReflectionClass($class);
        $this->assertEquals('full', $ref->getProperty('columnSpan')->getDefaultValue());
    }

    // ── Table Widgets: PausesWhenHidden trait ─────────────────────

    #[DataProvider('tableWidgetProvider')]
    public function test_table_widget_uses_pauses_when_hidden(string $class): void
    {
        $this->assertTrue(
            in_array(PausesWhenHidden::class, class_uses_recursive($class))
        );
    }

    // ── Table Widgets: entityChanged listener ────────────────────

    #[DataProvider('tableWidgetProvider')]
    public function test_table_widget_has_entity_changed_method(string $class): void
    {
        $this->assertTrue(method_exists($class, 'entityChanged'));
    }

    #[DataProvider('tableWidgetProvider')]
    public function test_table_widget_entity_changed_has_on_attribute(string $class): void
    {
        $ref = new \ReflectionMethod($class, 'entityChanged');
        $attributes = $ref->getAttributes(\Livewire\Attributes\On::class);

        $this->assertNotEmpty($attributes, "$class::entityChanged should have the #[On] attribute");
    }

    // ── Table Widgets: table() method exists ─────────────────────

    #[DataProvider('tableWidgetProvider')]
    public function test_table_widget_has_table_method(string $class): void
    {
        $this->assertTrue(method_exists($class, 'table'));
    }

    // ── All Widgets: getStats / getData / table declared ─────────

    #[DataProvider('statsOverviewWidgetProvider')]
    public function test_stats_widget_declares_get_stats(string $class): void
    {
        $ref = new \ReflectionClass($class);
        $this->assertTrue($ref->hasMethod('getStats'));
        $this->assertSame($class, $ref->getMethod('getStats')->getDeclaringClass()->getName());
    }

    #[DataProvider('chartWidgetProvider')]
    public function test_chart_widget_declares_get_data(string $class): void
    {
        $ref = new \ReflectionClass($class);
        $this->assertTrue($ref->hasMethod('getData'));
        $this->assertSame($class, $ref->getMethod('getData')->getDeclaringClass()->getName());
    }

    #[DataProvider('tableWidgetProvider')]
    public function test_table_widget_declares_table(string $class): void
    {
        $ref = new \ReflectionClass($class);
        $this->assertTrue($ref->hasMethod('table'));
        $this->assertSame($class, $ref->getMethod('table')->getDeclaringClass()->getName());
    }

    // ── All Widgets Combined: PausesWhenHidden ───────────────────

    /**
     * @return array<string, array{class-string}>
     */
    public static function allWidgetProvider(): array
    {
        return array_merge(
            self::statsOverviewWidgetProvider(),
            self::chartWidgetProvider(),
            self::tableWidgetProvider(),
        );
    }

    #[DataProvider('allWidgetProvider')]
    public function test_every_widget_uses_pauses_when_hidden(string $class): void
    {
        $this->assertTrue(
            in_array(PausesWhenHidden::class, class_uses_recursive($class)),
            "$class should use PausesWhenHidden trait"
        );
    }

    #[DataProvider('allWidgetProvider')]
    public function test_every_widget_has_entity_changed_listener(string $class): void
    {
        $ref = new \ReflectionMethod($class, 'entityChanged');
        $attributes = $ref->getAttributes(\Livewire\Attributes\On::class);

        $this->assertNotEmpty($attributes, "$class::entityChanged should listen for entity-changed");
    }

    #[DataProvider('allWidgetProvider')]
    public function test_every_widget_sort_is_not_null(string $class): void
    {
        $ref = new \ReflectionClass($class);
        $this->assertNotNull(
            $ref->getProperty('sort')->getDefaultValue(),
            "$class should have a non-null \$sort value"
        );
    }
}
