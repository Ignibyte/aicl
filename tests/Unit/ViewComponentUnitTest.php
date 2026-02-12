<?php

namespace Aicl\Tests\Unit;

use Aicl\View\Components\ActionBar;
use Aicl\View\Components\AlertBanner;
use Aicl\View\Components\AuthSplitLayout;
use Aicl\View\Components\CardGrid;
use Aicl\View\Components\Divider;
use Aicl\View\Components\EmptyState;
use Aicl\View\Components\InfoCard;
use Aicl\View\Components\KpiCard;
use Aicl\View\Components\MetadataList;
use Aicl\View\Components\ProgressCard;
use Aicl\View\Components\QuickAction;
use Aicl\View\Components\Spinner;
use Aicl\View\Components\SplitLayout;
use Aicl\View\Components\StatCard;
use Aicl\View\Components\StatsRow;
use Aicl\View\Components\StatusBadge;
use Aicl\View\Components\TabPanel;
use Aicl\View\Components\Tabs;
use Aicl\View\Components\Timeline;
use Aicl\View\Components\TrendCard;
use PHPUnit\Framework\TestCase;

class ViewComponentUnitTest extends TestCase
{
    // ─── SplitLayout ──────────────────────────────────────────

    public function test_split_layout_default_constructor(): void
    {
        $c = new SplitLayout;
        $this->assertEquals('2/3', $c->ratio);
        $this->assertFalse($c->reverse);
    }

    public function test_split_layout_main_cols_for_each_ratio(): void
    {
        $this->assertEquals('lg:col-span-8', (new SplitLayout(ratio: '2/3'))->mainCols());
        $this->assertEquals('lg:col-span-9', (new SplitLayout(ratio: '3/4'))->mainCols());
        $this->assertEquals('lg:col-span-6', (new SplitLayout(ratio: '1/2'))->mainCols());
        $this->assertEquals('lg:col-span-8', (new SplitLayout(ratio: 'invalid'))->mainCols());
    }

    public function test_split_layout_sidebar_cols_for_each_ratio(): void
    {
        $this->assertEquals('lg:col-span-4', (new SplitLayout(ratio: '2/3'))->sidebarCols());
        $this->assertEquals('lg:col-span-3', (new SplitLayout(ratio: '3/4'))->sidebarCols());
        $this->assertEquals('lg:col-span-6', (new SplitLayout(ratio: '1/2'))->sidebarCols());
        $this->assertEquals('lg:col-span-4', (new SplitLayout(ratio: 'unknown'))->sidebarCols());
    }

    // ─── CardGrid ─────────────────────────────────────────────

    public function test_card_grid_default_constructor(): void
    {
        $c = new CardGrid;
        $this->assertEquals(3, $c->cols);
        $this->assertEquals('6', $c->gap);
    }

    public function test_card_grid_grid_cols_for_known_values(): void
    {
        $this->assertStringContainsString('1', (new CardGrid(cols: 1))->gridCols());
        $this->assertStringContainsString('2', (new CardGrid(cols: 2))->gridCols());
        $this->assertStringContainsString('3', (new CardGrid(cols: 3))->gridCols());
        $this->assertStringContainsString('4', (new CardGrid(cols: 4))->gridCols());
    }

    public function test_card_grid_custom_gap(): void
    {
        $c = new CardGrid(gap: 'gap-8');
        $this->assertEquals('gap-8', $c->gap);
    }

    // ─── StatCard ─────────────────────────────────────────────

    public function test_stat_card_default_constructor(): void
    {
        $c = new StatCard(label: 'Users', value: 42);
        $this->assertEquals('Users', $c->label);
        $this->assertEquals(42, $c->value);
        $this->assertNull($c->trend);
        $this->assertNull($c->description);
    }

    public function test_stat_card_trend_color_up(): void
    {
        $c = new StatCard(label: 'T', value: 1, trend: 'up');
        $this->assertStringContainsString('green', $c->trendColor());
    }

    public function test_stat_card_trend_color_down(): void
    {
        $c = new StatCard(label: 'T', value: 1, trend: 'down');
        $this->assertStringContainsString('red', $c->trendColor());
    }

    public function test_stat_card_trend_color_flat(): void
    {
        $c = new StatCard(label: 'T', value: 1, trend: 'flat');
        $this->assertStringContainsString('gray', $c->trendColor());
    }

    public function test_stat_card_trend_color_null(): void
    {
        $c = new StatCard(label: 'T', value: 1);
        $this->assertStringContainsString('gray', $c->trendColor());
    }

    // ─── KpiCard ──────────────────────────────────────────────

    public function test_kpi_card_constructor(): void
    {
        $c = new KpiCard(label: 'Budget', actual: 75, target: 100);
        $this->assertEquals('Budget', $c->label);
        $this->assertEquals(75, $c->actual);
        $this->assertEquals(100, $c->target);
    }

    public function test_kpi_card_percentage_calculation(): void
    {
        $this->assertEquals(75.0, (new KpiCard(label: 'T', actual: 75, target: 100))->percentage());
        $this->assertEquals(100.0, (new KpiCard(label: 'T', actual: 150, target: 100))->percentage());
        $this->assertEquals(0.0, (new KpiCard(label: 'T', actual: 0, target: 100))->percentage());
    }

    public function test_kpi_card_percentage_with_zero_target(): void
    {
        $this->assertEquals(0.0, (new KpiCard(label: 'T', actual: 50, target: 0))->percentage());
    }

    public function test_kpi_card_progress_color(): void
    {
        $this->assertEquals('bg-green-500', (new KpiCard(label: 'T', actual: 85, target: 100))->progressColor());
        $this->assertEquals('bg-yellow-500', (new KpiCard(label: 'T', actual: 60, target: 100))->progressColor());
        $this->assertEquals('bg-red-500', (new KpiCard(label: 'T', actual: 30, target: 100))->progressColor());
    }

    public function test_kpi_card_progress_color_at_boundaries(): void
    {
        $this->assertEquals('bg-green-500', (new KpiCard(label: 'T', actual: 80, target: 100))->progressColor());
        $this->assertEquals('bg-yellow-500', (new KpiCard(label: 'T', actual: 50, target: 100))->progressColor());
        $this->assertEquals('bg-red-500', (new KpiCard(label: 'T', actual: 49, target: 100))->progressColor());
    }

    // ─── TrendCard ────────────────────────────────────────────

    public function test_trend_card_constructor(): void
    {
        $c = new TrendCard(label: 'Revenue', value: 1000, data: [10, 20, 30]);
        $this->assertEquals('Revenue', $c->label);
        $this->assertEquals(1000, $c->value);
        $this->assertEquals([10, 20, 30], $c->data);
    }

    public function test_trend_card_sparkline_path_with_data(): void
    {
        $c = new TrendCard(label: 'T', value: 1, data: [10, 20, 15, 30]);
        $path = $c->sparklinePath();
        $this->assertStringStartsWith('M ', $path);
        $this->assertStringContainsString('L ', $path);
    }

    public function test_trend_card_sparkline_path_empty_data(): void
    {
        $c = new TrendCard(label: 'T', value: 1, data: []);
        $this->assertEquals('', $c->sparklinePath());
    }

    public function test_trend_card_sparkline_path_single_point(): void
    {
        $c = new TrendCard(label: 'T', value: 1, data: [42]);
        $path = $c->sparklinePath();
        $this->assertStringStartsWith('M ', $path);
    }

    public function test_trend_card_sparkline_path_flat_data(): void
    {
        $c = new TrendCard(label: 'T', value: 1, data: [5, 5, 5, 5]);
        $path = $c->sparklinePath();
        $this->assertNotEmpty($path);
    }

    // ─── ProgressCard ─────────────────────────────────────────

    public function test_progress_card_constructor(): void
    {
        $c = new ProgressCard(label: 'Tasks', value: '75%', progress: 75);
        $this->assertEquals('Tasks', $c->label);
        $this->assertEquals('75%', $c->value);
        $this->assertEquals(75, $c->progress);
    }

    public function test_progress_card_width_clamps(): void
    {
        $this->assertEquals(0.0, (new ProgressCard(label: 'T', value: '0', progress: -10))->progressWidth());
        $this->assertEquals(0.0, (new ProgressCard(label: 'T', value: '0', progress: 0))->progressWidth());
        $this->assertEquals(50.0, (new ProgressCard(label: 'T', value: '50', progress: 50))->progressWidth());
        $this->assertEquals(100.0, (new ProgressCard(label: 'T', value: '100', progress: 100))->progressWidth());
        $this->assertEquals(100.0, (new ProgressCard(label: 'T', value: '150', progress: 150))->progressWidth());
    }

    // ─── StatusBadge ──────────────────────────────────────────

    public function test_status_badge_constructor(): void
    {
        $c = new StatusBadge(label: 'Active', color: 'success');
        $this->assertEquals('Active', $c->label);
        $this->assertEquals('success', $c->color);
    }

    public function test_status_badge_all_color_classes(): void
    {
        $colors = ['success', 'danger', 'warning', 'info', 'primary', 'gray'];
        foreach ($colors as $color) {
            $c = new StatusBadge(label: 'Test', color: $color);
            $this->assertNotEmpty($c->colorClasses(), "Color class for '{$color}' should not be empty");
        }
    }

    public function test_status_badge_color_aliases(): void
    {
        // 'green' is an alias for 'success'
        $green = new StatusBadge(label: 'T', color: 'green');
        $success = new StatusBadge(label: 'T', color: 'success');
        $this->assertEquals($green->colorClasses(), $success->colorClasses());

        // 'red' is an alias for 'danger'
        $red = new StatusBadge(label: 'T', color: 'red');
        $danger = new StatusBadge(label: 'T', color: 'danger');
        $this->assertEquals($red->colorClasses(), $danger->colorClasses());

        // 'blue' is an alias for 'info'
        $blue = new StatusBadge(label: 'T', color: 'blue');
        $info = new StatusBadge(label: 'T', color: 'info');
        $this->assertEquals($blue->colorClasses(), $info->colorClasses());
    }

    public function test_status_badge_unknown_color_fallback(): void
    {
        $c = new StatusBadge(label: 'T', color: 'nonexistent');
        $this->assertNotEmpty($c->colorClasses());
    }

    // ─── EmptyState ───────────────────────────────────────────

    public function test_empty_state_constructor(): void
    {
        $c = new EmptyState(heading: 'No data');
        $this->assertEquals('No data', $c->heading);
        $this->assertEquals('', $c->description);
        $this->assertEquals('heroicon-o-inbox', $c->icon);
        $this->assertNull($c->actionUrl);
        $this->assertNull($c->actionLabel);
    }

    public function test_empty_state_with_all_props(): void
    {
        $c = new EmptyState(
            heading: 'No items',
            description: 'Create one',
            icon: 'heroicon-o-plus',
            actionUrl: '/create',
            actionLabel: 'Create',
        );
        $this->assertEquals('No items', $c->heading);
        $this->assertEquals('Create one', $c->description);
        $this->assertEquals('heroicon-o-plus', $c->icon);
        $this->assertEquals('/create', $c->actionUrl);
        $this->assertEquals('Create', $c->actionLabel);
    }

    // ─── ActionBar ────────────────────────────────────────────

    public function test_action_bar_default_alignment(): void
    {
        $c = new ActionBar;
        $this->assertEquals('end', $c->align);
    }

    public function test_action_bar_align_class_values(): void
    {
        $this->assertStringContainsString('end', (new ActionBar(align: 'end'))->alignClass());
        $this->assertStringContainsString('start', (new ActionBar(align: 'start'))->alignClass());
        $this->assertStringContainsString('center', (new ActionBar(align: 'center'))->alignClass());
        $this->assertStringContainsString('between', (new ActionBar(align: 'between'))->alignClass());
    }

    public function test_action_bar_unknown_align_falls_back(): void
    {
        $c = new ActionBar(align: 'invalid');
        $this->assertNotEmpty($c->alignClass());
    }

    // ─── AlertBanner ──────────────────────────────────────────

    public function test_alert_banner_default_constructor(): void
    {
        $c = new AlertBanner;
        $this->assertEquals('info', $c->type);
        $this->assertTrue($c->dismissible);
        $this->assertNull($c->icon);
    }

    public function test_alert_banner_type_classes(): void
    {
        $types = ['info', 'success', 'warning', 'error', 'danger'];
        foreach ($types as $type) {
            $c = new AlertBanner(type: $type);
            $this->assertNotEmpty($c->typeClasses(), "Type class for '{$type}' should not be empty");
        }
    }

    public function test_alert_banner_error_equals_danger(): void
    {
        $error = new AlertBanner(type: 'error');
        $danger = new AlertBanner(type: 'danger');
        $this->assertEquals($error->typeClasses(), $danger->typeClasses());
    }

    public function test_alert_banner_default_icons(): void
    {
        $types = ['info', 'success', 'warning', 'error'];
        foreach ($types as $type) {
            $c = new AlertBanner(type: $type);
            $this->assertNotEmpty($c->defaultIcon(), "Default icon for '{$type}' should not be empty");
        }
    }

    public function test_alert_banner_custom_icon_overrides_default_icon(): void
    {
        $c = new AlertBanner(type: 'info', icon: 'heroicon-o-star');
        $this->assertEquals('heroicon-o-star', $c->icon);
    }

    // ─── Spinner ──────────────────────────────────────────────

    public function test_spinner_default_constructor(): void
    {
        $c = new Spinner;
        $this->assertEquals('md', $c->size);
        $this->assertEquals('primary', $c->color);
    }

    public function test_spinner_size_classes(): void
    {
        $sizes = ['sm', 'md', 'lg', 'xl'];
        foreach ($sizes as $size) {
            $c = new Spinner(size: $size);
            $this->assertNotEmpty($c->sizeClasses(), "Size class for '{$size}' should not be empty");
        }
    }

    public function test_spinner_color_classes(): void
    {
        $colors = ['primary', 'gray', 'white', 'success', 'danger', 'warning'];
        foreach ($colors as $color) {
            $c = new Spinner(color: $color);
            $this->assertNotEmpty($c->colorClasses(), "Color class for '{$color}' should not be empty");
        }
    }

    public function test_spinner_unknown_size_falls_back(): void
    {
        $c = new Spinner(size: 'xxl');
        $this->assertNotEmpty($c->sizeClasses());
    }

    public function test_spinner_unknown_color_falls_back(): void
    {
        $c = new Spinner(color: 'rainbow');
        $this->assertNotEmpty($c->colorClasses());
    }

    // ─── QuickAction ──────────────────────────────────────────

    public function test_quick_action_constructor(): void
    {
        $c = new QuickAction(icon: 'heroicon-o-star', label: 'Favorite');
        $this->assertEquals('heroicon-o-star', $c->icon);
        $this->assertEquals('Favorite', $c->label);
        $this->assertNull($c->href);
        $this->assertEquals('gray', $c->color);
    }

    public function test_quick_action_with_href(): void
    {
        $c = new QuickAction(icon: 'heroicon-o-link', label: 'Link', href: '/test');
        $this->assertEquals('/test', $c->href);
    }

    public function test_quick_action_custom_color(): void
    {
        $c = new QuickAction(icon: 'heroicon-o-star', label: 'Star', color: 'primary');
        $this->assertEquals('primary', $c->color);
    }

    // ─── StatsRow ─────────────────────────────────────────────

    public function test_stats_row_constructor(): void
    {
        $c = new StatsRow;
        $this->assertInstanceOf(StatsRow::class, $c);
    }

    // ─── AuthSplitLayout ──────────────────────────────────────

    public function test_auth_split_layout_default_constructor(): void
    {
        $c = new AuthSplitLayout;
        $this->assertNull($c->backgroundImage);
        $this->assertEquals('30', $c->overlayOpacity);
    }

    public function test_auth_split_layout_with_image(): void
    {
        $c = new AuthSplitLayout(backgroundImage: '/img/bg.jpg');
        $this->assertEquals('/img/bg.jpg', $c->backgroundImage);
    }

    public function test_auth_split_layout_custom_opacity(): void
    {
        $c = new AuthSplitLayout(overlayOpacity: '50');
        $this->assertEquals('50', $c->overlayOpacity);
    }

    // ─── Divider ──────────────────────────────────────────────

    public function test_divider_default_constructor(): void
    {
        $c = new Divider;
        $this->assertNull($c->label);
    }

    public function test_divider_with_label(): void
    {
        $c = new Divider(label: 'Section');
        $this->assertEquals('Section', $c->label);
    }

    // ─── Timeline ─────────────────────────────────────────────

    public function test_timeline_default_constructor(): void
    {
        $c = new Timeline;
        $this->assertEquals([], $c->entries);
    }

    public function test_timeline_with_entries(): void
    {
        $entries = [
            ['date' => '2026-01-01', 'title' => 'Created', 'color' => 'green'],
            ['date' => '2026-01-02', 'title' => 'Updated', 'color' => 'blue'],
        ];
        $c = new Timeline(entries: $entries);
        $this->assertCount(2, $c->entries);
        $this->assertEquals('Created', $c->entries[0]['title']);
    }

    // ─── MetadataList ─────────────────────────────────────────

    public function test_metadata_list_default_constructor(): void
    {
        $c = new MetadataList;
        $this->assertEquals([], $c->items);
    }

    public function test_metadata_list_with_items(): void
    {
        $c = new MetadataList(items: ['Key' => 'Value', 'Name' => 'Test']);
        $this->assertCount(2, $c->items);
        $this->assertEquals('Value', $c->items['Key']);
    }

    // ─── InfoCard ─────────────────────────────────────────────

    public function test_info_card_constructor(): void
    {
        $c = new InfoCard(heading: 'Details', items: ['A' => 'B']);
        $this->assertEquals('Details', $c->heading);
        $this->assertEquals(['A' => 'B'], $c->items);
    }

    // ─── Tabs ─────────────────────────────────────────────────

    public function test_tabs_default_constructor(): void
    {
        $c = new Tabs;
        $this->assertEquals('', $c->defaultTab);
        $this->assertEquals('underline', $c->variant);
    }

    public function test_tabs_pills_variant(): void
    {
        $c = new Tabs(variant: 'pills');
        $this->assertEquals('pills', $c->variant);
    }

    public function test_tabs_default_tab(): void
    {
        $c = new Tabs(defaultTab: 'overview');
        $this->assertEquals('overview', $c->defaultTab);
    }

    // ─── TabPanel ─────────────────────────────────────────────

    public function test_tab_panel_constructor(): void
    {
        $c = new TabPanel(name: 'overview', label: 'Overview');
        $this->assertEquals('overview', $c->name);
        $this->assertEquals('Overview', $c->label);
        $this->assertNull($c->icon);
    }

    public function test_tab_panel_with_icon(): void
    {
        $c = new TabPanel(name: 'settings', label: 'Settings', icon: 'heroicon-o-cog');
        $this->assertEquals('heroicon-o-cog', $c->icon);
    }
}
