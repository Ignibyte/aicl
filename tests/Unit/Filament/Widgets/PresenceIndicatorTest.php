<?php

namespace Aicl\Tests\Unit\Filament\Widgets;

use Aicl\Filament\Widgets\PresenceIndicator;
use PHPUnit\Framework\TestCase;

class PresenceIndicatorTest extends TestCase
{
    // ── $channelName ────────────────────────────────────────────

    public function test_channel_name_is_null_by_default(): void
    {
        $widget = new PresenceIndicator;

        $this->assertNull($widget->channelName);
    }

    // ── $view ───────────────────────────────────────────────────

    public function test_view_is_set_correctly(): void
    {
        $reflection = new \ReflectionClass(PresenceIndicator::class);
        $property = $reflection->getProperty('view');

        $this->assertFalse($property->isStatic());
        $this->assertSame('aicl::widgets.presence-indicator', $property->getDefaultValue());
    }

    // ── columnSpan ───────────────────────────────────────────────

    public function test_column_span_is_full(): void
    {
        $reflection = new \ReflectionClass(PresenceIndicator::class);
        $property = $reflection->getProperty('columnSpan');

        $this->assertSame('full', $property->getDefaultValue());
    }

    // ── mount() ─────────────────────────────────────────────────

    public function test_mount_defaults_to_admin_panel_channel(): void
    {
        $widget = new PresenceIndicator;
        $widget->mount();

        $this->assertSame('presence-admin-panel', $widget->channelName);
    }

    public function test_mount_sets_custom_channel_name(): void
    {
        $widget = new PresenceIndicator;
        $widget->mount('presence.projects.1');

        $this->assertSame('presence.projects.1', $widget->channelName);
    }
}
