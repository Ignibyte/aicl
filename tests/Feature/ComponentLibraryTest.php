<?php

namespace Aicl\Tests\Feature;

use Aicl\Livewire\ActivityFeed;
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
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class ComponentLibraryTest extends TestCase
{
    use RefreshDatabase;

    // ─── SplitLayout ──────────────────────────────────────────

    public function test_split_layout_default_ratio_is_two_thirds(): void
    {
        $component = new SplitLayout;

        $this->assertEquals('2/3', $component->ratio);
        $this->assertEquals('lg:col-span-8', $component->mainCols());
        $this->assertEquals('lg:col-span-4', $component->sidebarCols());
    }

    public function test_split_layout_three_quarters_ratio(): void
    {
        $component = new SplitLayout(ratio: '3/4');

        $this->assertEquals('lg:col-span-9', $component->mainCols());
        $this->assertEquals('lg:col-span-3', $component->sidebarCols());
    }

    public function test_split_layout_half_ratio(): void
    {
        $component = new SplitLayout(ratio: '1/2');

        $this->assertEquals('lg:col-span-6', $component->mainCols());
        $this->assertEquals('lg:col-span-6', $component->sidebarCols());
    }

    public function test_split_layout_renders(): void
    {
        $view = $this->blade('<x-aicl-split-layout><x-slot:main>Main</x-slot:main><x-slot:sidebar>Side</x-slot:sidebar></x-aicl-split-layout>');

        $view->assertSee('Main');
        $view->assertSee('Side');
    }

    // ─── CardGrid ─────────────────────────────────────────────

    public function test_card_grid_default_columns(): void
    {
        $component = new CardGrid;

        $this->assertEquals(3, $component->cols);
        $this->assertStringContainsString('lg:grid-cols-3', $component->gridCols());
    }

    public function test_card_grid_two_columns(): void
    {
        $component = new CardGrid(cols: 2);

        $this->assertStringContainsString('md:grid-cols-2', $component->gridCols());
        $this->assertStringNotContainsString('lg:grid-cols-3', $component->gridCols());
    }

    public function test_card_grid_four_columns(): void
    {
        $component = new CardGrid(cols: 4);

        $this->assertStringContainsString('lg:grid-cols-4', $component->gridCols());
    }

    public function test_card_grid_single_column(): void
    {
        $component = new CardGrid(cols: 1);

        $this->assertEquals('grid-cols-1', $component->gridCols());
    }

    public function test_card_grid_renders(): void
    {
        $view = $this->blade('<x-aicl-card-grid><div>Item</div></x-aicl-card-grid>');

        $view->assertSee('Item');
    }

    // ─── StatsRow ─────────────────────────────────────────────

    public function test_stats_row_renders(): void
    {
        $view = $this->blade('<x-aicl-stats-row><div>Stat</div></x-aicl-stats-row>');

        $view->assertSee('Stat');
    }

    // ─── EmptyState ───────────────────────────────────────────

    public function test_empty_state_renders_heading(): void
    {
        $view = $this->blade('<x-aicl-empty-state heading="No items" description="Nothing here" />');

        $view->assertSee('No items');
        $view->assertSee('Nothing here');
    }

    public function test_empty_state_renders_action_button(): void
    {
        $view = $this->blade('<x-aicl-empty-state heading="No items" action-url="/create" action-label="Create" />');

        $view->assertSee('Create');
        $view->assertSee('/create');
    }

    public function test_empty_state_omits_action_when_null(): void
    {
        $view = $this->blade('<x-aicl-empty-state heading="No items" />');

        $view->assertDontSee('</a>');
    }

    // ─── StatCard ─────────────────────────────────────────────

    public function test_stat_card_renders_label_and_value(): void
    {
        $view = $this->blade('<x-aicl-stat-card label="Total Projects" value="42" />');

        $view->assertSee('Total Projects');
        $view->assertSee('42');
    }

    public function test_stat_card_trend_up_color(): void
    {
        $component = new StatCard(label: 'Test', value: 10, trend: 'up');

        $this->assertStringContainsString('green', $component->trendColor());
        $this->assertEquals('heroicon-m-arrow-trending-up', $component->trendIcon());
    }

    public function test_stat_card_trend_down_color(): void
    {
        $component = new StatCard(label: 'Test', value: 10, trend: 'down');

        $this->assertStringContainsString('red', $component->trendColor());
        $this->assertEquals('heroicon-m-arrow-trending-down', $component->trendIcon());
    }

    public function test_stat_card_no_trend_defaults_to_gray(): void
    {
        $component = new StatCard(label: 'Test', value: 10);

        $this->assertStringContainsString('gray', $component->trendColor());
        $this->assertEquals('', $component->trendIcon());
    }

    // ─── KpiCard ──────────────────────────────────────────────

    public function test_kpi_card_calculates_percentage(): void
    {
        $component = new KpiCard(label: 'Budget', actual: 75, target: 100);

        $this->assertEquals(75.0, $component->percentage());
    }

    public function test_kpi_card_percentage_caps_at_100(): void
    {
        $component = new KpiCard(label: 'Budget', actual: 150, target: 100);

        $this->assertEquals(100.0, $component->percentage());
    }

    public function test_kpi_card_handles_zero_target(): void
    {
        $component = new KpiCard(label: 'Budget', actual: 50, target: 0);

        $this->assertEquals(0.0, $component->percentage());
    }

    public function test_kpi_card_progress_color_green_above_80(): void
    {
        $component = new KpiCard(label: 'Budget', actual: 85, target: 100);

        $this->assertEquals('bg-green-500', $component->progressColor());
    }

    public function test_kpi_card_progress_color_yellow_between_50_and_80(): void
    {
        $component = new KpiCard(label: 'Budget', actual: 60, target: 100);

        $this->assertEquals('bg-yellow-500', $component->progressColor());
    }

    public function test_kpi_card_progress_color_red_below_50(): void
    {
        $component = new KpiCard(label: 'Budget', actual: 30, target: 100);

        $this->assertEquals('bg-red-500', $component->progressColor());
    }

    // ─── TrendCard ────────────────────────────────────────────

    public function test_trend_card_generates_sparkline_path(): void
    {
        $component = new TrendCard(label: 'Trend', value: 42, data: [10, 20, 30, 20, 40]);

        $path = $component->sparklinePath();

        $this->assertStringStartsWith('M ', $path);
        $this->assertStringContainsString('L ', $path);
    }

    public function test_trend_card_empty_data_returns_empty_path(): void
    {
        $component = new TrendCard(label: 'Trend', value: 42, data: []);

        $this->assertEquals('', $component->sparklinePath());
    }

    public function test_trend_card_single_point_produces_valid_path(): void
    {
        $component = new TrendCard(label: 'Trend', value: 5, data: [5]);

        $path = $component->sparklinePath();

        $this->assertStringStartsWith('M ', $path);
    }

    // ─── ProgressCard ─────────────────────────────────────────

    public function test_progress_card_clamps_width_to_0_100(): void
    {
        $over = new ProgressCard(label: 'Test', value: '120%', progress: 120);
        $under = new ProgressCard(label: 'Test', value: '-5%', progress: -5);
        $normal = new ProgressCard(label: 'Test', value: '50%', progress: 50);

        $this->assertEquals(100.0, $over->progressWidth());
        $this->assertEquals(0.0, $under->progressWidth());
        $this->assertEquals(50.0, $normal->progressWidth());
    }

    // ─── MetadataList ─────────────────────────────────────────

    public function test_metadata_list_renders_key_value_pairs(): void
    {
        $view = $this->blade('<x-aicl-metadata-list :items="$items" />', [
            'items' => ['Name' => 'John', 'Role' => 'Admin'],
        ]);

        $view->assertSee('Name');
        $view->assertSee('John');
        $view->assertSee('Role');
        $view->assertSee('Admin');
    }

    public function test_metadata_list_handles_null_values(): void
    {
        $view = $this->blade('<x-aicl-metadata-list :items="$items" />', [
            'items' => ['Name' => 'John', 'Email' => null],
        ]);

        $view->assertSee('Name');
        $view->assertSee('John');
    }

    // ─── InfoCard ─────────────────────────────────────────────

    public function test_info_card_renders_heading_and_items(): void
    {
        $view = $this->blade('<x-aicl-info-card heading="Details" :items="$items" />', [
            'items' => ['Status' => 'Active'],
        ]);

        $view->assertSee('Details');
        $view->assertSee('Status');
        $view->assertSee('Active');
    }

    // ─── StatusBadge ──────────────────────────────────────────

    public function test_status_badge_renders_label(): void
    {
        $view = $this->blade('<x-aicl-status-badge label="Active" color="success" />');

        $view->assertSee('Active');
    }

    public function test_status_badge_color_classes(): void
    {
        $success = new StatusBadge(label: 'Active', color: 'success');
        $danger = new StatusBadge(label: 'Error', color: 'danger');
        $gray = new StatusBadge(label: 'Default', color: 'gray');
        $warning = new StatusBadge(label: 'Warn', color: 'warning');

        $this->assertStringContainsString('green', $success->colorClasses());
        $this->assertStringContainsString('red', $danger->colorClasses());
        $this->assertStringContainsString('gray', $gray->colorClasses());
        $this->assertStringContainsString('yellow', $warning->colorClasses());
    }

    public function test_status_badge_color_aliases(): void
    {
        $green = new StatusBadge(label: 'Test', color: 'green');
        $red = new StatusBadge(label: 'Test', color: 'red');
        $blue = new StatusBadge(label: 'Test', color: 'blue');
        $yellow = new StatusBadge(label: 'Test', color: 'yellow');

        $this->assertStringContainsString('green', $green->colorClasses());
        $this->assertStringContainsString('red', $red->colorClasses());
        $this->assertStringContainsString('blue', $blue->colorClasses());
        $this->assertStringContainsString('yellow', $yellow->colorClasses());
    }

    // ─── Timeline ─────────────────────────────────────────────

    public function test_timeline_renders_entries(): void
    {
        $view = $this->blade('<x-aicl-timeline :entries="$entries" />', [
            'entries' => [
                ['date' => '2026-01-15', 'title' => 'Created', 'description' => 'Project started', 'color' => 'green'],
                ['date' => '2026-01-20', 'title' => 'Updated', 'description' => 'Status changed', 'color' => 'blue'],
            ],
        ]);

        $view->assertSee('Created');
        $view->assertSee('Project started');
        $view->assertSee('Updated');
        $view->assertSee('Status changed');
    }

    public function test_timeline_handles_empty_entries(): void
    {
        $view = $this->blade('<x-aicl-timeline :entries="[]" />');

        $view->assertDontSee('Created');
    }

    // ─── ActionBar ────────────────────────────────────────────

    public function test_action_bar_default_alignment(): void
    {
        $component = new ActionBar;

        $this->assertEquals('justify-end', $component->alignClass());
    }

    public function test_action_bar_alignment_options(): void
    {
        $this->assertEquals('justify-start', (new ActionBar(align: 'start'))->alignClass());
        $this->assertEquals('justify-center', (new ActionBar(align: 'center'))->alignClass());
        $this->assertEquals('justify-between', (new ActionBar(align: 'between'))->alignClass());
        $this->assertEquals('justify-end', (new ActionBar(align: 'end'))->alignClass());
    }

    public function test_action_bar_renders_slot_content(): void
    {
        $view = $this->blade('<x-aicl-action-bar><button>Click</button></x-aicl-action-bar>');

        $view->assertSee('Click');
    }

    // ─── QuickAction ──────────────────────────────────────────

    public function test_quick_action_renders_as_button_without_href(): void
    {
        $view = $this->blade('<x-aicl-quick-action icon="heroicon-m-pencil" label="Edit" />');

        $view->assertSee('Edit');
        $view->assertSee('type="button"', false);
    }

    public function test_quick_action_renders_as_link_with_href(): void
    {
        $view = $this->blade('<x-aicl-quick-action icon="heroicon-m-pencil" label="Edit" href="/edit" />');

        $view->assertSee('/edit', false);
    }

    // ─── AlertBanner ──────────────────────────────────────────

    public function test_alert_banner_default_type_is_info(): void
    {
        $component = new AlertBanner;

        $this->assertEquals('info', $component->type);
        $this->assertStringContainsString('blue', $component->typeClasses());
        $this->assertEquals('heroicon-o-information-circle', $component->defaultIcon());
    }

    public function test_alert_banner_type_classes(): void
    {
        $this->assertStringContainsString('green', (new AlertBanner(type: 'success'))->typeClasses());
        $this->assertStringContainsString('yellow', (new AlertBanner(type: 'warning'))->typeClasses());
        $this->assertStringContainsString('red', (new AlertBanner(type: 'danger'))->typeClasses());
        $this->assertStringContainsString('red', (new AlertBanner(type: 'error'))->typeClasses());
    }

    public function test_alert_banner_type_icons(): void
    {
        $this->assertEquals('heroicon-o-check-circle', (new AlertBanner(type: 'success'))->defaultIcon());
        $this->assertEquals('heroicon-o-exclamation-triangle', (new AlertBanner(type: 'warning'))->defaultIcon());
        $this->assertEquals('heroicon-o-x-circle', (new AlertBanner(type: 'danger'))->defaultIcon());
    }

    public function test_alert_banner_custom_icon(): void
    {
        $component = new AlertBanner(icon: 'heroicon-o-bell');

        $this->assertEquals('heroicon-o-bell', $component->defaultIcon());
    }

    public function test_alert_banner_dismissible_by_default(): void
    {
        $component = new AlertBanner;

        $this->assertTrue($component->dismissible);
    }

    // ─── Divider ──────────────────────────────────────────────

    public function test_divider_renders_as_hr_without_label(): void
    {
        $view = $this->blade('<x-aicl-divider />');

        $view->assertSee('<hr', false);
    }

    public function test_divider_renders_label_when_provided(): void
    {
        $view = $this->blade('<x-aicl-divider label="Section Break" />');

        $view->assertSee('Section Break');
    }

    // ─── ActivityFeed Livewire Component ──────────────────────

    public function test_activity_feed_renders(): void
    {
        Activity::query()->delete();

        Livewire::test(ActivityFeed::class)
            ->assertSee('Recent Activity')
            ->assertSee('No activity recorded yet.');
    }

    public function test_activity_feed_shows_activity_entries(): void
    {
        $user = User::factory()->create();

        // Creating a user with HasAuditTrail generates activity
        $activity = Activity::where('subject_type', User::class)
            ->where('subject_id', $user->id)
            ->first();

        $this->assertNotNull($activity);

        Livewire::test(ActivityFeed::class)
            ->assertDontSee('No activity recorded yet.');
    }

    public function test_activity_feed_filters_by_subject_type(): void
    {
        $user = User::factory()->create();

        Livewire::test(ActivityFeed::class, ['subjectType' => User::class])
            ->assertDontSee('No activity recorded yet.');

        Livewire::test(ActivityFeed::class, ['subjectType' => 'App\\Models\\NonExistent'])
            ->assertSee('No activity recorded yet.');
    }

    public function test_activity_feed_custom_heading(): void
    {
        Livewire::test(ActivityFeed::class, ['heading' => 'User Activity'])
            ->assertSee('User Activity');
    }

    public function test_activity_feed_configurable_per_page(): void
    {
        $component = new ActivityFeed;

        $this->assertEquals(10, $component->perPage);
    }

    public function test_activity_feed_configurable_poll_interval(): void
    {
        $component = new ActivityFeed;

        $this->assertEquals(30, $component->pollInterval);
    }

    // ─── Spinner ─────────────────────────────────────────────

    public function test_spinner_default_size_is_md(): void
    {
        $component = new Spinner;

        $this->assertEquals('md', $component->size);
        $this->assertEquals('h-6 w-6', $component->sizeClasses());
    }

    public function test_spinner_size_classes(): void
    {
        $this->assertEquals('h-3 w-3', (new Spinner(size: 'xs'))->sizeClasses());
        $this->assertEquals('h-4 w-4', (new Spinner(size: 'sm'))->sizeClasses());
        $this->assertEquals('h-6 w-6', (new Spinner(size: 'md'))->sizeClasses());
        $this->assertEquals('h-8 w-8', (new Spinner(size: 'lg'))->sizeClasses());
        $this->assertEquals('h-12 w-12', (new Spinner(size: 'xl'))->sizeClasses());
    }

    public function test_spinner_default_color_is_primary(): void
    {
        $component = new Spinner;

        $this->assertEquals('primary', $component->color);
        $this->assertStringContainsString('primary', $component->colorClasses());
    }

    public function test_spinner_color_classes(): void
    {
        $this->assertStringContainsString('primary', (new Spinner(color: 'primary'))->colorClasses());
        $this->assertStringContainsString('white', (new Spinner(color: 'white'))->colorClasses());
        $this->assertStringContainsString('gray', (new Spinner(color: 'gray'))->colorClasses());
        $this->assertStringContainsString('green', (new Spinner(color: 'success'))->colorClasses());
        $this->assertStringContainsString('red', (new Spinner(color: 'danger'))->colorClasses());
        $this->assertStringContainsString('yellow', (new Spinner(color: 'warning'))->colorClasses());
        $this->assertStringContainsString('blue', (new Spinner(color: 'info'))->colorClasses());
    }

    public function test_spinner_color_aliases(): void
    {
        $this->assertStringContainsString('green', (new Spinner(color: 'green'))->colorClasses());
        $this->assertStringContainsString('red', (new Spinner(color: 'red'))->colorClasses());
        $this->assertStringContainsString('yellow', (new Spinner(color: 'yellow'))->colorClasses());
        $this->assertStringContainsString('blue', (new Spinner(color: 'blue'))->colorClasses());
    }

    public function test_spinner_renders_svg(): void
    {
        $view = $this->blade('<x-aicl-spinner />');

        $view->assertSee('animate-spin', false);
        $view->assertSee('viewBox="0 0 24 24"', false);
    }

    public function test_spinner_renders_with_custom_attributes(): void
    {
        $view = $this->blade('<x-aicl-spinner size="sm" color="white" class="mr-2" />');

        $view->assertSee('animate-spin', false);
    }

    // ─── AuthSplitLayout ─────────────────────────────────────

    public function test_auth_split_layout_default_overlay_opacity(): void
    {
        $component = new AuthSplitLayout;

        $this->assertNull($component->backgroundImage);
        $this->assertEquals('30', $component->overlayOpacity);
    }

    public function test_auth_split_layout_renders_gradient_without_image(): void
    {
        $view = $this->blade('<x-aicl-auth-split-layout>Form content here</x-aicl-auth-split-layout>');

        $view->assertSee('Form content here');
        $view->assertSee('bg-gradient-to-br', false);
    }

    public function test_auth_split_layout_renders_image_when_provided(): void
    {
        $view = $this->blade('<x-aicl-auth-split-layout background-image="/images/bg.jpg">Form content</x-aicl-auth-split-layout>');

        $view->assertSee('/images/bg.jpg', false);
        $view->assertSee('Form content');
    }

    public function test_auth_split_layout_renders_overlay_slot(): void
    {
        $view = $this->blade('
            <x-aicl-auth-split-layout>
                Form content
                <x-slot:overlay>
                    <p>Welcome overlay</p>
                </x-slot:overlay>
            </x-aicl-auth-split-layout>
        ');

        $view->assertSee('Welcome overlay');
    }

    // ─── Tabs ───────────────────────────────────────────────

    public function test_tabs_default_variant_is_underline(): void
    {
        $component = new Tabs;

        $this->assertEquals('underline', $component->variant);
        $this->assertEquals('', $component->defaultTab);
    }

    public function test_tabs_accepts_pills_variant(): void
    {
        $component = new Tabs(variant: 'pills');

        $this->assertEquals('pills', $component->variant);
    }

    public function test_tabs_accepts_default_tab(): void
    {
        $component = new Tabs(defaultTab: 'settings');

        $this->assertEquals('settings', $component->defaultTab);
    }

    public function test_tabs_renders_with_tab_panels(): void
    {
        $view = $this->blade('
            <x-aicl-tabs default-tab="first">
                <x-aicl-tab-panel name="first" label="First">
                    <p>First panel content</p>
                </x-aicl-tab-panel>
                <x-aicl-tab-panel name="second" label="Second">
                    <p>Second panel content</p>
                </x-aicl-tab-panel>
            </x-aicl-tabs>
        ');

        $view->assertSee('First panel content');
        $view->assertSee('Second panel content');
        $view->assertSee('data-tab-name="first"', false);
        $view->assertSee('data-tab-name="second"', false);
        $view->assertSee('data-tab-label="First"', false);
        $view->assertSee('data-tab-label="Second"', false);
    }

    public function test_tabs_renders_underline_variant_classes(): void
    {
        $view = $this->blade('
            <x-aicl-tabs>
                <x-aicl-tab-panel name="tab1" label="Tab 1">Content</x-aicl-tab-panel>
            </x-aicl-tabs>
        ');

        $view->assertSee('border-b', false);
        $view->assertSee('border-b-2', false);
    }

    public function test_tabs_renders_pills_variant_classes(): void
    {
        $view = $this->blade('
            <x-aicl-tabs variant="pills">
                <x-aicl-tab-panel name="tab1" label="Tab 1">Content</x-aicl-tab-panel>
            </x-aicl-tabs>
        ');

        $view->assertSee('rounded-lg', false);
        $view->assertDontSee('border-b-2');
    }

    public function test_tabs_includes_alpine_data(): void
    {
        $view = $this->blade('
            <x-aicl-tabs default-tab="overview">
                <x-aicl-tab-panel name="overview" label="Overview">Content</x-aicl-tab-panel>
            </x-aicl-tabs>
        ');

        $view->assertSee('x-data', false);
        $view->assertSee("activeTab: 'overview'", false);
    }

    public function test_tabs_renders_aria_attributes(): void
    {
        $view = $this->blade('
            <x-aicl-tabs>
                <x-aicl-tab-panel name="panel1" label="Panel 1">Content</x-aicl-tab-panel>
            </x-aicl-tabs>
        ');

        $view->assertSee('role="tablist"', false);
        $view->assertSee('role="tabpanel"', false);
        $view->assertSee('role="tab"', false);
    }

    // ─── TabPanel ───────────────────────────────────────────

    public function test_tab_panel_stores_name_and_label(): void
    {
        $component = new TabPanel(name: 'settings', label: 'Settings');

        $this->assertEquals('settings', $component->name);
        $this->assertEquals('Settings', $component->label);
        $this->assertNull($component->icon);
    }

    public function test_tab_panel_accepts_icon(): void
    {
        $component = new TabPanel(name: 'settings', label: 'Settings', icon: 'heroicon-o-cog');

        $this->assertEquals('heroicon-o-cog', $component->icon);
    }

    public function test_tab_panel_renders_data_attributes(): void
    {
        $view = $this->blade('
            <x-aicl-tabs>
                <x-aicl-tab-panel name="details" label="Details">
                    Panel content here
                </x-aicl-tab-panel>
            </x-aicl-tabs>
        ');

        $view->assertSee('data-tab-name="details"', false);
        $view->assertSee('data-tab-label="Details"', false);
        $view->assertSee('Panel content here');
    }

    public function test_tab_panel_uses_x_show_for_visibility(): void
    {
        $view = $this->blade('
            <x-aicl-tabs>
                <x-aicl-tab-panel name="test" label="Test">Content</x-aicl-tab-panel>
            </x-aicl-tabs>
        ');

        $view->assertSee("x-show=\"activeTab === 'test'\"", false);
        $view->assertSee('x-cloak', false);
    }

    public function test_tab_panel_has_transition(): void
    {
        $view = $this->blade('
            <x-aicl-tabs>
                <x-aicl-tab-panel name="test" label="Test">Content</x-aicl-tab-panel>
            </x-aicl-tabs>
        ');

        $view->assertSee('x-transition:enter', false);
    }
}
