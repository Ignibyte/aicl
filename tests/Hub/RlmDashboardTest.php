<?php

namespace Aicl\Tests\Hub;

use Aicl\Filament\Pages\RlmDashboard;
use Aicl\Filament\Widgets\CategoryBreakdownChart;
use Aicl\Filament\Widgets\FailureTrendChart;
use Aicl\Filament\Widgets\ProjectHealthWidget;
use Aicl\Filament\Widgets\PromotionQueueWidget;
use Filament\Pages\Page;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\TableWidget;
use PHPUnit\Framework\TestCase;

class RlmDashboardTest extends TestCase
{
    // ─── Page Structure ─────────────────────────────────────

    public function test_rlm_dashboard_extends_page(): void
    {
        $this->assertTrue(is_subclass_of(RlmDashboard::class, Page::class));
    }

    public function test_rlm_dashboard_slug(): void
    {
        $reflection = new \ReflectionClass(RlmDashboard::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertEquals('rlm-dashboard', $defaults['slug']);
    }

    public function test_rlm_dashboard_navigation_group(): void
    {
        $reflection = new \ReflectionClass(RlmDashboard::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertEquals('RLM Hub', $defaults['navigationGroup']);
    }

    public function test_rlm_dashboard_navigation_sort(): void
    {
        $reflection = new \ReflectionClass(RlmDashboard::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertEquals(1, $defaults['navigationSort']);
    }

    public function test_rlm_dashboard_has_blade_view(): void
    {
        $reflection = new \ReflectionClass(RlmDashboard::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertEquals('aicl::filament.pages.rlm-dashboard', $defaults['view']);
    }

    public function test_rlm_dashboard_defines_header_widgets(): void
    {
        $this->assertTrue(method_exists(RlmDashboard::class, 'getHeaderWidgets'));
    }

    public function test_rlm_dashboard_defines_footer_widgets(): void
    {
        $this->assertTrue(method_exists(RlmDashboard::class, 'getFooterWidgets'));
    }

    // ─── Widget Types ───────────────────────────────────────

    public function test_failure_trend_chart_extends_chart_widget(): void
    {
        $this->assertTrue(is_subclass_of(FailureTrendChart::class, ChartWidget::class));
    }

    public function test_failure_trend_chart_type_is_line(): void
    {
        $chart = new FailureTrendChart;

        $reflection = new \ReflectionMethod($chart, 'getType');
        $reflection->setAccessible(true);

        $this->assertEquals('line', $reflection->invoke($chart));
    }

    public function test_category_breakdown_chart_extends_chart_widget(): void
    {
        $this->assertTrue(is_subclass_of(CategoryBreakdownChart::class, ChartWidget::class));
    }

    public function test_category_breakdown_chart_type_is_doughnut(): void
    {
        $chart = new CategoryBreakdownChart;

        $reflection = new \ReflectionMethod($chart, 'getType');
        $reflection->setAccessible(true);

        $this->assertEquals('doughnut', $reflection->invoke($chart));
    }

    public function test_promotion_queue_extends_table_widget(): void
    {
        $this->assertTrue(is_subclass_of(PromotionQueueWidget::class, TableWidget::class));
    }

    public function test_promotion_queue_has_full_column_span(): void
    {
        $reflection = new \ReflectionClass(PromotionQueueWidget::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertEquals('full', $defaults['columnSpan']);
    }

    public function test_project_health_extends_stats_overview(): void
    {
        $this->assertTrue(is_subclass_of(ProjectHealthWidget::class, StatsOverviewWidget::class));
    }

    public function test_project_health_defines_get_stats(): void
    {
        $this->assertTrue(method_exists(ProjectHealthWidget::class, 'getStats'));
    }

    // ─── Plugin Registration ────────────────────────────────

    public function test_rlm_dashboard_registered_in_plugin(): void
    {
        $plugin = new \Aicl\AiclPlugin;

        $reflection = new \ReflectionMethod($plugin, 'getPages');
        $reflection->setAccessible(true);

        $pages = $reflection->invoke($plugin);

        $this->assertContains(RlmDashboard::class, $pages);
    }

    public function test_dashboard_widgets_registered_in_plugin(): void
    {
        $plugin = new \Aicl\AiclPlugin;

        $reflection = new \ReflectionMethod($plugin, 'getWidgets');
        $reflection->setAccessible(true);

        $widgets = $reflection->invoke($plugin);

        $this->assertContains(FailureTrendChart::class, $widgets);
        $this->assertContains(CategoryBreakdownChart::class, $widgets);
        $this->assertContains(PromotionQueueWidget::class, $widgets);
        $this->assertContains(ProjectHealthWidget::class, $widgets);
    }
}
