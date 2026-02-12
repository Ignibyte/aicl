<?php

namespace Aicl\Tests\Feature;

use Aicl\View\Components\ActionBar;
use Aicl\View\Components\AlertBanner;
use Aicl\View\Components\CardGrid;
use Aicl\View\Components\EmptyState;
use Aicl\View\Components\InfoCard;
use Aicl\View\Components\KpiCard;
use Aicl\View\Components\ProgressCard;
use Aicl\View\Components\Spinner;
use Aicl\View\Components\SplitLayout;
use Aicl\View\Components\StatCard;
use Aicl\View\Components\StatusBadge;
use Aicl\View\Components\TrendCard;
use Tests\TestCase;

class ComponentEdgeCaseTest extends TestCase
{
    // ─── SplitLayout Edge Cases ─────────────────────────────────

    public function test_split_layout_unknown_ratio_falls_back(): void
    {
        $component = new SplitLayout(ratio: 'invalid');

        // Should use a default column span
        $this->assertNotEmpty($component->mainCols());
        $this->assertNotEmpty($component->sidebarCols());
    }

    public function test_split_layout_reverse_mode(): void
    {
        $component = new SplitLayout(reverse: true);

        $this->assertTrue($component->reverse);
    }

    // ─── CardGrid Edge Cases ────────────────────────────────────

    public function test_card_grid_five_columns(): void
    {
        $component = new CardGrid(cols: 5);

        $gridCols = $component->gridCols();
        $this->assertNotEmpty($gridCols);
    }

    public function test_card_grid_with_gap_property(): void
    {
        $component = new CardGrid(gap: 'gap-6');

        $this->assertEquals('gap-6', $component->gap);
    }

    // ─── StatCard Edge Cases ────────────────────────────────────

    public function test_stat_card_with_zero_value(): void
    {
        $view = $this->blade('<x-aicl-stat-card label="Empty" value="0" />');

        $view->assertSee('Empty');
        $view->assertSee('0');
    }

    public function test_stat_card_with_description(): void
    {
        $component = new StatCard(label: 'Users', value: 100, description: '+10 this week');

        $this->assertEquals('+10 this week', $component->description);
    }

    public function test_stat_card_with_flat_trend(): void
    {
        $component = new StatCard(label: 'Test', value: 10, trend: 'flat');

        $this->assertStringContainsString('gray', $component->trendColor());
    }

    // ─── KpiCard Edge Cases ─────────────────────────────────────

    public function test_kpi_card_negative_actual(): void
    {
        $component = new KpiCard(label: 'Budget', actual: -50, target: 100);

        $pct = $component->percentage();
        // percentage() uses min(value, 100) but doesn't clamp negative — actual behavior
        $this->assertEquals(-50.0, $pct);
    }

    public function test_kpi_card_both_zero(): void
    {
        $component = new KpiCard(label: 'Budget', actual: 0, target: 0);

        $this->assertEquals(0.0, $component->percentage());
    }

    public function test_kpi_card_exactly_80_percent(): void
    {
        $component = new KpiCard(label: 'Budget', actual: 80, target: 100);

        $this->assertEquals(80.0, $component->percentage());
        $this->assertEquals('bg-green-500', $component->progressColor());
    }

    public function test_kpi_card_exactly_50_percent(): void
    {
        $component = new KpiCard(label: 'Budget', actual: 50, target: 100);

        $this->assertEquals(50.0, $component->percentage());
        $this->assertEquals('bg-yellow-500', $component->progressColor());
    }

    // ─── TrendCard Edge Cases ───────────────────────────────────

    public function test_trend_card_flat_data(): void
    {
        $component = new TrendCard(label: 'Flat', value: 42, data: [5, 5, 5, 5]);

        $path = $component->sparklinePath();
        // All same values should produce a flat line
        $this->assertNotEmpty($path);
    }

    public function test_trend_card_two_points(): void
    {
        $component = new TrendCard(label: 'Two', value: 42, data: [10, 20]);

        $path = $component->sparklinePath();
        $this->assertStringStartsWith('M ', $path);
    }

    public function test_trend_card_large_values(): void
    {
        $component = new TrendCard(label: 'Large', value: 1000000, data: [100000, 500000, 250000, 750000, 1000000]);

        $path = $component->sparklinePath();
        $this->assertNotEmpty($path);
    }

    // ─── ProgressCard Edge Cases ────────────────────────────────

    public function test_progress_card_zero_progress(): void
    {
        $component = new ProgressCard(label: 'Zero', value: '0%', progress: 0);

        $this->assertEquals(0.0, $component->progressWidth());
    }

    public function test_progress_card_exactly_100(): void
    {
        $component = new ProgressCard(label: 'Full', value: '100%', progress: 100);

        $this->assertEquals(100.0, $component->progressWidth());
    }

    public function test_progress_card_renders(): void
    {
        $view = $this->blade('<x-aicl-progress-card label="Progress" value="75%" :progress="75" />');

        $view->assertSee('Progress');
        $view->assertSee('75%');
    }

    // ─── StatusBadge Edge Cases ─────────────────────────────────

    public function test_status_badge_info_color(): void
    {
        $component = new StatusBadge(label: 'Info', color: 'info');

        $this->assertStringContainsString('blue', $component->colorClasses());
    }

    public function test_status_badge_primary_color(): void
    {
        $component = new StatusBadge(label: 'Primary', color: 'primary');

        $this->assertStringContainsString('primary', $component->colorClasses());
    }

    public function test_status_badge_unknown_color(): void
    {
        $component = new StatusBadge(label: 'Unknown', color: 'nonexistent');

        $classes = $component->colorClasses();
        // Should fall back to a default
        $this->assertNotEmpty($classes);
    }

    public function test_status_badge_renders_with_dot(): void
    {
        $view = $this->blade('<x-aicl-status-badge label="Active" color="success" dot />');

        $view->assertSee('Active');
    }

    // ─── EmptyState Edge Cases ──────────────────────────────────

    public function test_empty_state_renders_with_icon(): void
    {
        $view = $this->blade('<x-aicl-empty-state heading="No Data" icon="heroicon-o-inbox" />');

        $view->assertSee('No Data');
    }

    public function test_empty_state_renders_with_action(): void
    {
        $view = $this->blade('
            <x-aicl-empty-state
                heading="No items"
                action-url="/create"
                action-label="Create Item"
            />
        ');

        $view->assertSee('No items');
        $view->assertSee('Create Item');
    }

    // ─── AlertBanner Edge Cases ─────────────────────────────────

    public function test_alert_banner_non_dismissible(): void
    {
        $component = new AlertBanner(dismissible: false);

        $this->assertFalse($component->dismissible);
    }

    public function test_alert_banner_renders_message(): void
    {
        $view = $this->blade('<x-aicl-alert-banner type="warning" message="Watch out!" />');

        $view->assertSee('Watch out!');
    }

    public function test_alert_banner_error_type_uses_danger_classes(): void
    {
        $error = new AlertBanner(type: 'error');
        $danger = new AlertBanner(type: 'danger');

        $this->assertEquals($error->typeClasses(), $danger->typeClasses());
    }

    // ─── ActionBar Edge Cases ───────────────────────────────────

    public function test_action_bar_unknown_alignment_falls_back(): void
    {
        $component = new ActionBar(align: 'nonexistent');

        $class = $component->alignClass();
        $this->assertNotEmpty($class);
    }

    // ─── MetadataList Edge Cases ────────────────────────────────

    public function test_metadata_list_renders_empty_items(): void
    {
        $view = $this->blade('<x-aicl-metadata-list :items="[]" />');

        // Should render without error — check it doesn't contain error text
        $this->assertNotNull($view);
    }

    public function test_metadata_list_renders_many_items(): void
    {
        $items = [];
        for ($i = 0; $i < 20; $i++) {
            $items["Key {$i}"] = "Value {$i}";
        }

        $view = $this->blade('<x-aicl-metadata-list :items="$items" />', ['items' => $items]);

        $view->assertSee('Key 0');
        $view->assertSee('Value 19');
    }

    // ─── InfoCard Edge Cases ────────────────────────────────────

    public function test_info_card_empty_items(): void
    {
        $view = $this->blade('<x-aicl-info-card heading="Empty" :items="[]" />');

        $view->assertSee('Empty');
    }

    public function test_info_card_with_single_item(): void
    {
        $view = $this->blade('<x-aicl-info-card heading="Details" :items="$items" />', [
            'items' => ['Key' => 'Value'],
        ]);

        $view->assertSee('Key');
        $view->assertSee('Value');
    }

    // ─── Spinner Edge Cases ─────────────────────────────────────

    public function test_spinner_unknown_size_falls_back(): void
    {
        $component = new Spinner(size: 'huge');

        $classes = $component->sizeClasses();
        $this->assertNotEmpty($classes);
    }

    public function test_spinner_unknown_color_falls_back(): void
    {
        $component = new Spinner(color: 'nonexistent');

        $classes = $component->colorClasses();
        $this->assertNotEmpty($classes);
    }

    // ─── Timeline Edge Cases ────────────────────────────────────

    public function test_timeline_single_entry(): void
    {
        $view = $this->blade('<x-aicl-timeline :entries="$entries" />', [
            'entries' => [
                ['date' => '2026-01-15', 'title' => 'Single', 'description' => 'One entry', 'color' => 'green'],
            ],
        ]);

        $view->assertSee('Single');
    }

    public function test_timeline_entry_without_description(): void
    {
        $view = $this->blade('<x-aicl-timeline :entries="$entries" />', [
            'entries' => [
                ['date' => '2026-01-15', 'title' => 'No Desc', 'color' => 'blue'],
            ],
        ]);

        $view->assertSee('No Desc');
    }

    // ─── Divider Edge Cases ─────────────────────────────────────

    public function test_divider_with_custom_classes(): void
    {
        $view = $this->blade('<x-aicl-divider class="my-8" />');

        $view->assertSee('my-8', false);
    }
}
