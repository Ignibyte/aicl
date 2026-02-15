<?php

namespace Aicl\Tests\Unit\Filament\Widgets;

use Aicl\Filament\Widgets\Traits\PausesWhenHidden;
use PHPUnit\Framework\TestCase;

class PausesWhenHiddenTest extends TestCase
{
    public function test_get_polling_interval_returns_null(): void
    {
        $widget = new PausesWhenHiddenTestWidget;

        $this->assertNull($widget->getPollingInterval());
    }

    public function test_default_visibility_polling_interval_is_60(): void
    {
        $widget = new PausesWhenHiddenTestWidget;

        $this->assertSame(60, $widget->visibilityPollingInterval());
    }

    public function test_custom_visibility_polling_interval(): void
    {
        $widget = new PausesWhenHiddenCustomIntervalWidget;

        $this->assertSame(30, $widget->visibilityPollingInterval());
    }

    public function test_trait_methods_exist(): void
    {
        $widget = new PausesWhenHiddenTestWidget;

        $this->assertTrue(method_exists($widget, 'getPollingInterval'));
        $this->assertTrue(method_exists($widget, 'visibilityPollingInterval'));
        $this->assertTrue(method_exists($widget, 'bootPausesWhenHidden'));
    }

    public function test_trait_is_applied_to_stats_widget(): void
    {
        $uses = class_uses(\Aicl\Filament\Widgets\RlmPatternStatsOverview::class);

        $this->assertContains(PausesWhenHidden::class, $uses);
    }

    public function test_trait_is_applied_to_chart_widget(): void
    {
        $uses = class_uses(\Aicl\Filament\Widgets\FailureTrendChart::class);

        $this->assertContains(PausesWhenHidden::class, $uses);
    }

    public function test_trait_is_applied_to_table_widget(): void
    {
        $uses = class_uses(\Aicl\Filament\Widgets\RecentGenerationTracesWidget::class);

        $this->assertContains(PausesWhenHidden::class, $uses);
    }
}

// ── Test Stubs ────────────────────────────────────────────────────

class PausesWhenHiddenTestWidget
{
    use PausesWhenHidden;
}

class PausesWhenHiddenCustomIntervalWidget
{
    use PausesWhenHidden;

    public function visibilityPollingInterval(): int
    {
        return 30;
    }
}
