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

        $this->assertTrue((new \ReflectionClass($widget))->hasMethod('getPollingInterval'));
        $this->assertTrue((new \ReflectionClass($widget))->hasMethod('visibilityPollingInterval'));
        $this->assertTrue((new \ReflectionClass($widget))->hasMethod('bootPausesWhenHidden'));
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
