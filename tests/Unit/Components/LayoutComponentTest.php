<?php

namespace Aicl\Tests\Unit\Components;

use Aicl\View\Components\CardGrid;
use Aicl\View\Components\EmptyState;
use Aicl\View\Components\SplitLayout;
use Aicl\View\Components\StatsRow;
use PHPUnit\Framework\TestCase;

class LayoutComponentTest extends TestCase
{
    // ─── SplitLayout ────────────────────────────────────────

    public function test_split_layout_default_ratio(): void
    {
        $component = new SplitLayout;

        $this->assertStringContainsString('col-span-8', $component->mainCols());
        $this->assertStringContainsString('col-span-4', $component->sidebarCols());
    }

    public function test_split_layout_three_quarters_ratio(): void
    {
        $component = new SplitLayout(ratio: '3/4');

        $this->assertStringContainsString('col-span-9', $component->mainCols());
        $this->assertStringContainsString('col-span-3', $component->sidebarCols());
    }

    public function test_split_layout_half_ratio(): void
    {
        $component = new SplitLayout(ratio: '1/2');

        $this->assertStringContainsString('col-span-6', $component->mainCols());
        $this->assertStringContainsString('col-span-6', $component->sidebarCols());
    }

    public function test_split_layout_reverse_property(): void
    {
        $component = new SplitLayout(reverse: true);

        $this->assertTrue($component->reverse);
    }

    // ─── CardGrid ───────────────────────────────────────────

    public function test_card_grid_default_cols(): void
    {
        $component = new CardGrid;

        $this->assertStringContainsString('grid-cols-1', $component->gridCols());
    }

    public function test_card_grid_two_cols(): void
    {
        $component = new CardGrid(cols: 2);

        $gridCols = $component->gridCols();
        $this->assertStringContainsString('md:grid-cols-2', $gridCols);
    }

    public function test_card_grid_four_cols(): void
    {
        $component = new CardGrid(cols: 4);

        $gridCols = $component->gridCols();
        $this->assertStringContainsString('lg:grid-cols-4', $gridCols);
    }

    public function test_card_grid_gap_property(): void
    {
        $component = new CardGrid(gap: '4');

        $this->assertEquals('4', $component->gap);
    }

    // ─── StatsRow ───────────────────────────────────────────

    public function test_stats_row_can_be_instantiated(): void
    {
        $component = new StatsRow;

        $this->assertInstanceOf(StatsRow::class, $component);
    }

    // ─── EmptyState ─────────────────────────────────────────

    public function test_empty_state_requires_heading(): void
    {
        $component = new EmptyState(heading: 'No results');

        $this->assertEquals('No results', $component->heading);
    }

    public function test_empty_state_default_description(): void
    {
        $component = new EmptyState(heading: 'Empty');

        $this->assertEquals('', $component->description);
    }

    public function test_empty_state_default_icon(): void
    {
        $component = new EmptyState(heading: 'Empty');

        $this->assertEquals('heroicon-o-inbox', $component->icon);
    }

    public function test_empty_state_with_action(): void
    {
        $component = new EmptyState(
            heading: 'Empty',
            actionUrl: '/create',
            actionLabel: 'Create New',
        );

        $this->assertEquals('/create', $component->actionUrl);
        $this->assertEquals('Create New', $component->actionLabel);
    }

    public function test_empty_state_action_defaults_to_null(): void
    {
        $component = new EmptyState(heading: 'Empty');

        $this->assertNull($component->actionUrl);
        $this->assertNull($component->actionLabel);
    }
}
