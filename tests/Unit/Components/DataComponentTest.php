<?php

namespace Aicl\Tests\Unit\Components;

use Aicl\View\Components\InfoCard;
use Aicl\View\Components\MetadataList;
use Aicl\View\Components\StatusBadge;
use Aicl\View\Components\Timeline;
use PHPUnit\Framework\TestCase;

class DataComponentTest extends TestCase
{
    // ─── StatusBadge ────────────────────────────────────────

    public function test_status_badge_stores_label_and_color(): void
    {
        $component = new StatusBadge(label: 'Active', color: 'success');

        $this->assertEquals('Active', $component->label);
        $this->assertEquals('success', $component->color);
    }

    public function test_status_badge_default_color(): void
    {
        $component = new StatusBadge(label: 'Unknown');

        $this->assertEquals('gray', $component->color);
    }

    public function test_status_badge_color_classes_returns_string(): void
    {
        $component = new StatusBadge(label: 'Active', color: 'success');

        $classes = $component->colorClasses();
        $this->assertNotEmpty($classes);
    }

    public function test_status_badge_color_classes_for_different_colors(): void
    {
        $colors = ['gray', 'primary', 'success', 'warning', 'danger', 'info'];

        foreach ($colors as $color) {
            $component = new StatusBadge(label: 'Test', color: $color);
            $classes = $component->colorClasses();
            $this->assertNotEmpty($classes, "Empty classes for color: {$color}");
        }
    }

    public function test_status_badge_optional_icon(): void
    {
        $component = new StatusBadge(label: 'Active', icon: 'heroicon-o-check');

        $this->assertEquals('heroicon-o-check', $component->icon);
    }

    public function test_status_badge_icon_defaults_to_null(): void
    {
        $component = new StatusBadge(label: 'Test');

        $this->assertNull($component->icon);
    }

    // ─── MetadataList ───────────────────────────────────────

    public function test_metadata_list_stores_items(): void
    {
        $items = ['Name' => 'Project', 'Status' => 'Active'];
        $component = new MetadataList(items: $items);

        $this->assertEquals($items, $component->items);
    }

    public function test_metadata_list_defaults_to_empty_array(): void
    {
        $component = new MetadataList;

        $this->assertEquals([], $component->items);
    }

    // ─── InfoCard ───────────────────────────────────────────

    public function test_info_card_stores_heading(): void
    {
        $component = new InfoCard(heading: 'Details');

        $this->assertEquals('Details', $component->heading);
    }

    public function test_info_card_stores_items(): void
    {
        $items = ['Key' => 'Value'];
        $component = new InfoCard(heading: 'Test', items: $items);

        $this->assertEquals($items, $component->items);
    }

    public function test_info_card_optional_icon(): void
    {
        $component = new InfoCard(heading: 'Test', icon: 'heroicon-o-info');

        $this->assertEquals('heroicon-o-info', $component->icon);
    }

    public function test_info_card_icon_defaults_to_null(): void
    {
        $component = new InfoCard(heading: 'Test');

        $this->assertNull($component->icon);
    }

    // ─── Timeline ───────────────────────────────────────────

    public function test_timeline_stores_entries(): void
    {
        $entries = [
            ['date' => '2026-01-01', 'title' => 'Created'],
            ['date' => '2026-01-02', 'title' => 'Updated'],
        ];

        $component = new Timeline(entries: $entries);

        $this->assertCount(2, $component->entries);
    }

    public function test_timeline_defaults_to_empty_entries(): void
    {
        $component = new Timeline;

        $this->assertEquals([], $component->entries);
    }
}
