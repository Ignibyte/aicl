<?php

namespace Aicl\Tests\Unit\Components;

use Aicl\View\Components\AuthSplitLayout;
use Aicl\View\Components\Spinner;
use PHPUnit\Framework\TestCase;

class MiscComponentTest extends TestCase
{
    // ─── Spinner ────────────────────────────────────────────

    public function test_spinner_default_size(): void
    {
        $component = new Spinner;

        $this->assertEquals('md', $component->size);
    }

    public function test_spinner_default_color(): void
    {
        $component = new Spinner;

        $this->assertEquals('primary', $component->color);
    }

    public function test_spinner_size_classes(): void
    {
        $sizes = ['xs', 'sm', 'md', 'lg', 'xl'];

        foreach ($sizes as $size) {
            $component = new Spinner(size: $size);
            $classes = $component->sizeClasses();
            $this->assertNotEmpty($classes, "Empty size classes for size: {$size}");
        }
    }

    public function test_spinner_color_classes(): void
    {
        $colors = ['primary', 'success', 'warning', 'danger'];

        foreach ($colors as $color) {
            $component = new Spinner(color: $color);
            $classes = $component->colorClasses();
            $this->assertNotEmpty($classes, "Empty color classes for color: {$color}");
        }
    }

    // ─── AuthSplitLayout ────────────────────────────────────

    public function test_auth_split_layout_background_image_defaults_to_null(): void
    {
        $component = new AuthSplitLayout;

        $this->assertNull($component->backgroundImage);
    }

    public function test_auth_split_layout_overlay_opacity_default(): void
    {
        $component = new AuthSplitLayout;

        $this->assertEquals('30', $component->overlayOpacity);
    }

    public function test_auth_split_layout_custom_background_image(): void
    {
        $component = new AuthSplitLayout(backgroundImage: '/images/bg.jpg');

        $this->assertEquals('/images/bg.jpg', $component->backgroundImage);
    }

    public function test_auth_split_layout_custom_opacity(): void
    {
        $component = new AuthSplitLayout(overlayOpacity: '50');

        $this->assertEquals('50', $component->overlayOpacity);
    }
}
