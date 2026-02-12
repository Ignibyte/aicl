<?php

namespace Aicl\Tests\Unit\Components;

use Aicl\View\Components\KpiCard;
use Aicl\View\Components\ProgressCard;
use Aicl\View\Components\StatCard;
use Aicl\View\Components\TrendCard;
use PHPUnit\Framework\TestCase;

class MetricComponentTest extends TestCase
{
    // ─── StatCard ───────────────────────────────────────────

    public function test_stat_card_stores_label_and_value(): void
    {
        $component = new StatCard(label: 'Users', value: 42);

        $this->assertEquals('Users', $component->label);
        $this->assertEquals(42, $component->value);
    }

    public function test_stat_card_default_color(): void
    {
        $component = new StatCard(label: 'X', value: 0);

        $this->assertEquals('primary', $component->color);
    }

    public function test_stat_card_icon_bg_class_returns_string(): void
    {
        $component = new StatCard(label: 'X', value: 0, color: 'success');

        $this->assertIsString($component->iconBgClass());
        $this->assertNotEmpty($component->iconBgClass());
    }

    public function test_stat_card_icon_text_class_returns_string(): void
    {
        $component = new StatCard(label: 'X', value: 0);

        $this->assertIsString($component->iconTextClass());
        $this->assertNotEmpty($component->iconTextClass());
    }

    public function test_stat_card_trend_color_up(): void
    {
        $component = new StatCard(label: 'X', value: 0, trend: 'up');

        $this->assertStringContainsString('green', $component->trendColor());
    }

    public function test_stat_card_trend_color_down(): void
    {
        $component = new StatCard(label: 'X', value: 0, trend: 'down');

        $this->assertStringContainsString('red', $component->trendColor());
    }

    public function test_stat_card_trend_icon_up(): void
    {
        $component = new StatCard(label: 'X', value: 0, trend: 'up');

        $icon = $component->trendIcon();
        $this->assertNotEmpty($icon);
    }

    public function test_stat_card_trend_icon_down(): void
    {
        $component = new StatCard(label: 'X', value: 0, trend: 'down');

        $icon = $component->trendIcon();
        $this->assertNotEmpty($icon);
    }

    // ─── KpiCard ────────────────────────────────────────────

    public function test_kpi_card_stores_label_actual_target(): void
    {
        $component = new KpiCard(label: 'Revenue', actual: 75, target: 100);

        $this->assertEquals('Revenue', $component->label);
        $this->assertEquals(75, $component->actual);
        $this->assertEquals(100, $component->target);
    }

    public function test_kpi_card_percentage_calculation(): void
    {
        $component = new KpiCard(label: 'X', actual: 75, target: 100);

        $this->assertEquals(75.0, $component->percentage());
    }

    public function test_kpi_card_percentage_clamped_to_100(): void
    {
        $component = new KpiCard(label: 'X', actual: 150, target: 100);

        $this->assertEquals(100.0, $component->percentage());
    }

    public function test_kpi_card_percentage_with_negative_actual(): void
    {
        $component = new KpiCard(label: 'X', actual: -10, target: 100);

        // Negative actual produces negative percentage (not clamped at model level)
        $this->assertLessThan(0, $component->percentage());
    }

    public function test_kpi_card_progress_color_green_above_80(): void
    {
        $component = new KpiCard(label: 'X', actual: 90, target: 100);

        $this->assertStringContainsString('green', $component->progressColor());
    }

    public function test_kpi_card_progress_color_yellow_50_to_79(): void
    {
        $component = new KpiCard(label: 'X', actual: 60, target: 100);

        $this->assertStringContainsString('yellow', $component->progressColor());
    }

    public function test_kpi_card_progress_color_red_below_50(): void
    {
        $component = new KpiCard(label: 'X', actual: 30, target: 100);

        $this->assertStringContainsString('red', $component->progressColor());
    }

    // ─── TrendCard ──────────────────────────────────────────

    public function test_trend_card_stores_properties(): void
    {
        $component = new TrendCard(label: 'Sales', value: 1000, data: [10, 20, 30]);

        $this->assertEquals('Sales', $component->label);
        $this->assertEquals(1000, $component->value);
        $this->assertEquals([10, 20, 30], $component->data);
    }

    public function test_trend_card_sparkline_path_returns_string(): void
    {
        $component = new TrendCard(label: 'X', value: 0, data: [10, 20, 30, 40]);

        $path = $component->sparklinePath();
        $this->assertIsString($path);
    }

    public function test_trend_card_sparkline_path_empty_data(): void
    {
        $component = new TrendCard(label: 'X', value: 0, data: []);

        $path = $component->sparklinePath();
        $this->assertIsString($path);
    }

    public function test_trend_card_sparkline_class(): void
    {
        $component = new TrendCard(label: 'X', value: 0, color: 'success');

        $this->assertIsString($component->sparklineClass());
    }

    // ─── ProgressCard ───────────────────────────────────────

    public function test_progress_card_stores_properties(): void
    {
        $component = new ProgressCard(label: 'Tasks', value: '8/10', progress: 80);

        $this->assertEquals('Tasks', $component->label);
        $this->assertEquals('8/10', $component->value);
        $this->assertEquals(80, $component->progress);
    }

    public function test_progress_card_width_clamps_to_100(): void
    {
        $component = new ProgressCard(label: 'X', value: 0, progress: 150);

        $this->assertEquals(100.0, $component->progressWidth());
    }

    public function test_progress_card_width_clamps_to_zero(): void
    {
        $component = new ProgressCard(label: 'X', value: 0, progress: -10);

        $this->assertEquals(0.0, $component->progressWidth());
    }

    public function test_progress_card_bar_class(): void
    {
        $component = new ProgressCard(label: 'X', value: 0, progress: 50, color: 'danger');

        $this->assertIsString($component->progressBarClass());
        $this->assertNotEmpty($component->progressBarClass());
    }
}
