<?php

namespace Aicl\Tests\Unit\Components;

use Aicl\View\Components\ActionBar;
use Aicl\View\Components\AlertBanner;
use Aicl\View\Components\Divider;
use Aicl\View\Components\QuickAction;
use PHPUnit\Framework\TestCase;

class ActionComponentTest extends TestCase
{
    // ─── ActionBar ──────────────────────────────────────────

    public function test_action_bar_default_align(): void
    {
        $component = new ActionBar;

        $this->assertEquals('end', $component->align);
    }

    public function test_action_bar_align_class_end(): void
    {
        $component = new ActionBar(align: 'end');

        $this->assertStringContainsString('justify-end', $component->alignClass());
    }

    public function test_action_bar_align_class_start(): void
    {
        $component = new ActionBar(align: 'start');

        $this->assertStringContainsString('justify-start', $component->alignClass());
    }

    public function test_action_bar_align_class_center(): void
    {
        $component = new ActionBar(align: 'center');

        $this->assertStringContainsString('justify-center', $component->alignClass());
    }

    public function test_action_bar_align_class_between(): void
    {
        $component = new ActionBar(align: 'between');

        $this->assertStringContainsString('justify-between', $component->alignClass());
    }

    // ─── AlertBanner ────────────────────────────────────────

    public function test_alert_banner_default_type(): void
    {
        $component = new AlertBanner;

        $this->assertEquals('info', $component->type);
    }

    public function test_alert_banner_default_dismissible(): void
    {
        $component = new AlertBanner;

        $this->assertTrue($component->dismissible);
    }

    public function test_alert_banner_type_classes_for_all_types(): void
    {
        $types = ['success', 'warning', 'danger', 'info'];

        foreach ($types as $type) {
            $component = new AlertBanner(type: $type);
            $classes = $component->typeClasses();
            $this->assertNotEmpty($classes, "Empty classes for type: {$type}");
        }
    }

    public function test_alert_banner_default_icon_for_all_types(): void
    {
        $types = ['success', 'warning', 'danger', 'info'];

        foreach ($types as $type) {
            $component = new AlertBanner(type: $type);
            $icon = $component->defaultIcon();
            $this->assertNotEmpty($icon, "Empty icon for type: {$type}");
        }
    }

    public function test_alert_banner_custom_icon_overrides_default(): void
    {
        $component = new AlertBanner(icon: 'heroicon-o-star');

        $this->assertEquals('heroicon-o-star', $component->icon);
    }

    // ─── QuickAction ────────────────────────────────────────

    public function test_quick_action_stores_properties(): void
    {
        $component = new QuickAction(
            icon: 'heroicon-o-plus',
            label: 'Create',
            href: '/create',
            color: 'primary',
        );

        $this->assertEquals('heroicon-o-plus', $component->icon);
        $this->assertEquals('Create', $component->label);
        $this->assertEquals('/create', $component->href);
        $this->assertEquals('primary', $component->color);
    }

    public function test_quick_action_href_defaults_to_null(): void
    {
        $component = new QuickAction(icon: 'heroicon-o-plus', label: 'Create');

        $this->assertNull($component->href);
    }

    public function test_quick_action_default_color(): void
    {
        $component = new QuickAction(icon: 'heroicon-o-plus', label: 'Create');

        $this->assertEquals('gray', $component->color);
    }

    // ─── Divider ────────────────────────────────────────────

    public function test_divider_with_label(): void
    {
        $component = new Divider(label: 'Section');

        $this->assertEquals('Section', $component->label);
    }

    public function test_divider_label_defaults_to_null(): void
    {
        $component = new Divider;

        $this->assertNull($component->label);
    }
}
