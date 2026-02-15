<?php

namespace Aicl\Tests\Unit\Filament\Widgets;

use Aicl\Filament\Widgets\PollingWidget;
use PHPUnit\Framework\TestCase;

class PollingWidgetTest extends TestCase
{
    // ── pollingInterval() ───────────────────────────────────────

    public function test_default_polling_interval(): void
    {
        $widget = new TestPollingWidget;

        $this->assertSame(60, $widget->pollingInterval());
    }

    public function test_custom_polling_interval(): void
    {
        $widget = new TestPollingWidgetCustomInterval;

        $this->assertSame(30, $widget->pollingInterval());
    }

    // ── pauseWhenHidden() ───────────────────────────────────────

    public function test_default_pause_when_hidden(): void
    {
        $widget = new TestPollingWidget;

        $this->assertTrue($widget->pauseWhenHidden());
    }

    // ── $view ───────────────────────────────────────────────────

    public function test_view_is_set_correctly(): void
    {
        $reflection = new \ReflectionClass(PollingWidget::class);
        $property = $reflection->getProperty('view');

        $this->assertFalse($property->isStatic());
        $this->assertSame('aicl::widgets.polling-widget', $property->getDefaultValue());
    }
}

// ── Test Stubs ────────────────────────────────────────────────────

class TestPollingWidget extends PollingWidget
{
    // Uses all defaults
}

class TestPollingWidgetCustomInterval extends PollingWidget
{
    public function pollingInterval(): int
    {
        return 30;
    }
}
