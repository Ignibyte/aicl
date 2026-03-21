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
        $this->assertTrue((new \ReflectionClass(QueueStatsWidget::class))->isSubclassOf(StatsOverviewWidget::class));
    }

    public function test_queue_stats_defines_get_stats(): void
    {
        $this->assertTrue((new \ReflectionClass(QueueStatsWidget::class))->hasMethod('getStats'));
    }

    public function test_queue_stats_defines_get_queue_size(): void
    {
        $reflection = new \ReflectionClass(QueueStatsWidget::class);

        $this->assertTrue($reflection->hasMethod('getQueueSize'));
    }

    // ─── RecentFailedJobsWidget ─────────────────────────────

    public function test_recent_failed_jobs_extends_widget(): void
    {
        $this->assertTrue((new \ReflectionClass(RecentFailedJobsWidget::class))->isSubclassOf(Widget::class));
    }

    public function test_recent_failed_jobs_defines_table(): void
    {
        $this->assertTrue((new \ReflectionClass(RecentFailedJobsWidget::class))->hasMethod('table'));
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
        $this->assertTrue((new \ReflectionClass(GlobalSearchWidget::class))->isSubclassOf(Widget::class));
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
        $this->assertTrue((new \ReflectionClass(GlobalSearchWidget::class))->hasMethod('results'));
    }

    public function test_global_search_defines_clear_search(): void
    {
        $this->assertTrue((new \ReflectionClass(GlobalSearchWidget::class))->hasMethod('clearSearch'));
    }
}
