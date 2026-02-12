<?php

namespace Aicl\Tests\Unit\Filament\Widgets;

use Aicl\Filament\Widgets\GlobalSearchWidget;
use Aicl\Filament\Widgets\QueueStatsWidget;
use Aicl\Filament\Widgets\RecentFailedJobsWidget;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\Widget;
use PHPUnit\Framework\TestCase;

class WidgetTest extends TestCase
{
    // ─── QueueStatsWidget ───────────────────────────────────

    public function test_queue_stats_extends_stats_overview(): void
    {
        $this->assertTrue(is_subclass_of(QueueStatsWidget::class, StatsOverviewWidget::class));
    }

    public function test_queue_stats_defines_get_stats(): void
    {
        $this->assertTrue(method_exists(QueueStatsWidget::class, 'getStats'));
    }

    public function test_queue_stats_defines_get_queue_size(): void
    {
        $reflection = new \ReflectionClass(QueueStatsWidget::class);

        $this->assertTrue($reflection->hasMethod('getQueueSize'));
    }

    // ─── RecentFailedJobsWidget ─────────────────────────────

    public function test_recent_failed_jobs_extends_widget(): void
    {
        $this->assertTrue(is_subclass_of(RecentFailedJobsWidget::class, Widget::class));
    }

    public function test_recent_failed_jobs_defines_table(): void
    {
        $this->assertTrue(method_exists(RecentFailedJobsWidget::class, 'table'));
    }

    public function test_recent_failed_jobs_has_full_column_span(): void
    {
        $reflection = new \ReflectionClass(RecentFailedJobsWidget::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertEquals('full', $defaults['columnSpan']);
    }

    // ─── GlobalSearchWidget ─────────────────────────────────

    public function test_global_search_extends_widget(): void
    {
        $this->assertTrue(is_subclass_of(GlobalSearchWidget::class, Widget::class));
    }

    public function test_global_search_default_properties(): void
    {
        $reflection = new \ReflectionClass(GlobalSearchWidget::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertEquals('', $defaults['query']);
        $this->assertFalse($defaults['showResults']);
        $this->assertEquals('full', $defaults['columnSpan']);
    }

    public function test_global_search_defines_results_method(): void
    {
        $this->assertTrue(method_exists(GlobalSearchWidget::class, 'results'));
    }

    public function test_global_search_defines_clear_search(): void
    {
        $this->assertTrue(method_exists(GlobalSearchWidget::class, 'clearSearch'));
    }
}
