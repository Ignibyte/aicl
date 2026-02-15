<?php

namespace Aicl\Tests\Unit\View\Components;

use Aicl\View\Components\ActionBar;
use Aicl\View\Components\AlertBanner;
use Aicl\View\Components\AuthSplitLayout;
use Aicl\View\Components\CardGrid;
use Aicl\View\Components\Divider;
use Aicl\View\Components\EmptyState;
use Aicl\View\Components\IgnibyteLogo;
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
use Illuminate\View\Component;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ViewComponentTest extends TestCase
{
    // ========================================================================
    // Data Provider Tests — All Components
    // ========================================================================

    #[DataProvider('componentProvider')]
    public function test_component_extends_base_class(string $componentClass): void
    {
        $this->assertTrue(is_subclass_of($componentClass, Component::class));
    }

    #[DataProvider('componentProvider')]
    public function test_component_has_render_method(string $componentClass): void
    {
        $this->assertTrue(method_exists($componentClass, 'render'));
    }

    #[DataProvider('componentWithViewProvider')]
    public function test_component_render_returns_correct_view(string $componentClass, array $constructorArgs, string $expectedView): void
    {
        $component = new $componentClass(...$constructorArgs);
        $view = $component->render();

        $this->assertInstanceOf(\Illuminate\View\View::class, $view);
        $this->assertEquals($expectedView, $view->name());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function componentProvider(): array
    {
        return [
            'ActionBar' => [ActionBar::class],
            'AlertBanner' => [AlertBanner::class],
            'AuthSplitLayout' => [AuthSplitLayout::class],
            'CardGrid' => [CardGrid::class],
            'Divider' => [Divider::class],
            'EmptyState' => [EmptyState::class],
            'IgnibyteLogo' => [IgnibyteLogo::class],
            'InfoCard' => [InfoCard::class],
            'KpiCard' => [KpiCard::class],
            'MetadataList' => [MetadataList::class],
            'ProgressCard' => [ProgressCard::class],
            'QuickAction' => [QuickAction::class],
            'Spinner' => [Spinner::class],
            'SplitLayout' => [SplitLayout::class],
            'StatCard' => [StatCard::class],
            'StatsRow' => [StatsRow::class],
            'StatusBadge' => [StatusBadge::class],
            'TabPanel' => [TabPanel::class],
            'Tabs' => [Tabs::class],
            'Timeline' => [Timeline::class],
            'TrendCard' => [TrendCard::class],
        ];
    }

    /**
     * @return array<string, array{string, array<string, mixed>, string}>
     */
    public static function componentWithViewProvider(): array
    {
        return [
            'ActionBar' => [ActionBar::class, [], 'aicl::components.action-bar'],
            'AlertBanner' => [AlertBanner::class, [], 'aicl::components.alert-banner'],
            'AuthSplitLayout' => [AuthSplitLayout::class, [], 'aicl::components.auth-split-layout'],
            'CardGrid' => [CardGrid::class, [], 'aicl::components.card-grid'],
            'Divider' => [Divider::class, [], 'aicl::components.divider'],
            'EmptyState' => [EmptyState::class, ['heading' => 'No data'], 'aicl::components.empty-state'],
            'IgnibyteLogo' => [IgnibyteLogo::class, [], 'aicl::components.ignibyte-logo'],
            'InfoCard' => [InfoCard::class, ['heading' => 'Info'], 'aicl::components.info-card'],
            'KpiCard' => [KpiCard::class, ['label' => 'KPI', 'actual' => 50, 'target' => 100], 'aicl::components.kpi-card'],
            'MetadataList' => [MetadataList::class, [], 'aicl::components.metadata-list'],
            'ProgressCard' => [ProgressCard::class, ['label' => 'Progress', 'value' => '50%', 'progress' => 50.0], 'aicl::components.progress-card'],
            'QuickAction' => [QuickAction::class, ['icon' => 'heroicon-o-star', 'label' => 'Star'], 'aicl::components.quick-action'],
            'Spinner' => [Spinner::class, [], 'aicl::components.spinner'],
            'SplitLayout' => [SplitLayout::class, [], 'aicl::components.split-layout'],
            'StatCard' => [StatCard::class, ['label' => 'Users', 'value' => 42], 'aicl::components.stat-card'],
            'StatsRow' => [StatsRow::class, [], 'aicl::components.stats-row'],
            'StatusBadge' => [StatusBadge::class, ['label' => 'Active'], 'aicl::components.status-badge'],
            'TabPanel' => [TabPanel::class, ['name' => 'tab1', 'label' => 'Tab 1'], 'aicl::components.tab-panel'],
            'Tabs' => [Tabs::class, [], 'aicl::components.tabs'],
            'Timeline' => [Timeline::class, [], 'aicl::components.timeline'],
            'TrendCard' => [TrendCard::class, ['label' => 'Revenue', 'value' => 1000], 'aicl::components.trend-card'],
        ];
    }

    // ========================================================================
    // IgnibyteLogo — Helper Methods
    // ========================================================================

    #[DataProvider('logoSizeProvider')]
    public function test_ignibyte_logo_height_for_each_size(string $size, string $expectedClass): void
    {
        $component = new IgnibyteLogo(size: $size);

        $this->assertEquals($expectedClass, $component->logoHeight());
    }

    #[DataProvider('logoSizeProvider')]
    public function test_ignibyte_logo_text_size_for_each_size(string $size, string $expectedHeight): void
    {
        $component = new IgnibyteLogo(size: $size);

        $this->assertNotEmpty($component->textSize());
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function logoSizeProvider(): array
    {
        return [
            'sm' => ['sm', 'h-6'],
            'md' => ['md', 'h-10'],
            'lg' => ['lg', 'h-16'],
            'xl' => ['xl', 'h-24'],
            'unknown defaults to md' => ['unknown', 'h-10'],
        ];
    }

    public function test_ignibyte_logo_text_size_mapping(): void
    {
        $this->assertEquals('text-xl', (new IgnibyteLogo(size: 'sm'))->textSize());
        $this->assertEquals('text-3xl', (new IgnibyteLogo(size: 'md'))->textSize());
        $this->assertEquals('text-5xl', (new IgnibyteLogo(size: 'lg'))->textSize());
        $this->assertEquals('text-7xl', (new IgnibyteLogo(size: 'xl'))->textSize());
        $this->assertEquals('text-3xl', (new IgnibyteLogo(size: 'invalid'))->textSize());
    }

    public function test_ignibyte_logo_icon_only_mode(): void
    {
        $component = new IgnibyteLogo(iconOnly: true);

        $this->assertTrue($component->iconOnly);
    }

    public function test_ignibyte_logo_default_is_not_icon_only(): void
    {
        $component = new IgnibyteLogo;

        $this->assertFalse($component->iconOnly);
    }

    public function test_ignibyte_logo_url_returns_string(): void
    {
        $component = new IgnibyteLogo;

        $this->assertIsString($component->logoUrl());
    }

    public function test_ignibyte_logo_brand_name_returns_string(): void
    {
        $component = new IgnibyteLogo;

        $this->assertIsString($component->brandName());
        $this->assertNotEmpty($component->brandName());
    }

    // ========================================================================
    // StatCard — Helper Methods
    // ========================================================================

    #[DataProvider('statCardColorProvider')]
    public function test_stat_card_icon_bg_class_for_each_color(string $color): void
    {
        $component = new StatCard(label: 'Test', value: 1, color: $color);

        $this->assertNotEmpty($component->iconBgClass());
    }

    #[DataProvider('statCardColorProvider')]
    public function test_stat_card_icon_text_class_for_each_color(string $color): void
    {
        $component = new StatCard(label: 'Test', value: 1, color: $color);

        $this->assertNotEmpty($component->iconTextClass());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function statCardColorProvider(): array
    {
        return [
            'primary' => ['primary'],
            'success' => ['success'],
            'green' => ['green'],
            'warning' => ['warning'],
            'yellow' => ['yellow'],
            'danger' => ['danger'],
            'red' => ['red'],
            'info' => ['info'],
            'blue' => ['blue'],
            'unknown' => ['unknown'],
        ];
    }

    public function test_stat_card_color_aliases_match(): void
    {
        $this->assertEquals(
            (new StatCard(label: 'T', value: 1, color: 'success'))->iconBgClass(),
            (new StatCard(label: 'T', value: 1, color: 'green'))->iconBgClass()
        );
        $this->assertEquals(
            (new StatCard(label: 'T', value: 1, color: 'danger'))->iconTextClass(),
            (new StatCard(label: 'T', value: 1, color: 'red'))->iconTextClass()
        );
        $this->assertEquals(
            (new StatCard(label: 'T', value: 1, color: 'info'))->iconBgClass(),
            (new StatCard(label: 'T', value: 1, color: 'blue'))->iconBgClass()
        );
    }

    public function test_stat_card_trend_icon_returns_heroicon_for_up(): void
    {
        $component = new StatCard(label: 'T', value: 1, trend: 'up');

        $this->assertStringContainsString('arrow-trending-up', $component->trendIcon());
    }

    public function test_stat_card_trend_icon_returns_heroicon_for_down(): void
    {
        $component = new StatCard(label: 'T', value: 1, trend: 'down');

        $this->assertStringContainsString('arrow-trending-down', $component->trendIcon());
    }

    public function test_stat_card_trend_icon_returns_empty_for_null(): void
    {
        $component = new StatCard(label: 'T', value: 1);

        $this->assertEquals('', $component->trendIcon());
    }

    public function test_stat_card_accepts_string_value(): void
    {
        $component = new StatCard(label: 'Revenue', value: '$1,234');

        $this->assertEquals('$1,234', $component->value);
    }

    public function test_stat_card_accepts_float_value(): void
    {
        $component = new StatCard(label: 'Average', value: 99.5);

        $this->assertEquals(99.5, $component->value);
    }

    // ========================================================================
    // ProgressCard — Helper Methods
    // ========================================================================

    #[DataProvider('progressBarColorProvider')]
    public function test_progress_card_bar_class_for_each_color(string $color, string $expectedContains): void
    {
        $component = new ProgressCard(label: 'T', value: '50%', progress: 50.0, color: $color);

        $this->assertStringContainsString($expectedContains, $component->progressBarClass());
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function progressBarColorProvider(): array
    {
        return [
            'primary' => ['primary', 'primary-500'],
            'success' => ['success', 'green-500'],
            'green' => ['green', 'green-500'],
            'warning' => ['warning', 'yellow-500'],
            'yellow' => ['yellow', 'yellow-500'],
            'danger' => ['danger', 'red-500'],
            'red' => ['red', 'red-500'],
            'info' => ['info', 'blue-500'],
            'blue' => ['blue', 'blue-500'],
            'unknown' => ['unknown', 'gray-500'],
        ];
    }

    public function test_progress_card_description_is_optional(): void
    {
        $component = new ProgressCard(label: 'T', value: '50%', progress: 50.0);

        $this->assertNull($component->description);
    }

    public function test_progress_card_description_can_be_set(): void
    {
        $component = new ProgressCard(label: 'T', value: '50%', progress: 50.0, description: 'Halfway there');

        $this->assertEquals('Halfway there', $component->description);
    }

    // ========================================================================
    // Spinner — Helper Methods
    // ========================================================================

    public function test_spinner_xs_size_class(): void
    {
        $component = new Spinner(size: 'xs');

        $this->assertEquals('h-3 w-3', $component->sizeClasses());
    }

    public function test_spinner_color_aliases_match(): void
    {
        $this->assertEquals(
            (new Spinner(color: 'success'))->colorClasses(),
            (new Spinner(color: 'green'))->colorClasses()
        );
        $this->assertEquals(
            (new Spinner(color: 'danger'))->colorClasses(),
            (new Spinner(color: 'red'))->colorClasses()
        );
        $this->assertEquals(
            (new Spinner(color: 'warning'))->colorClasses(),
            (new Spinner(color: 'yellow'))->colorClasses()
        );
        $this->assertEquals(
            (new Spinner(color: 'info'))->colorClasses(),
            (new Spinner(color: 'blue'))->colorClasses()
        );
    }

    // ========================================================================
    // AlertBanner — Helper Methods
    // ========================================================================

    public function test_alert_banner_default_icon_returns_custom_when_set(): void
    {
        $component = new AlertBanner(icon: 'heroicon-o-star');

        $this->assertEquals('heroicon-o-star', $component->defaultIcon());
    }

    public function test_alert_banner_default_icon_info(): void
    {
        $component = new AlertBanner(type: 'info');

        $this->assertStringContainsString('information-circle', $component->defaultIcon());
    }

    public function test_alert_banner_default_icon_success(): void
    {
        $component = new AlertBanner(type: 'success');

        $this->assertStringContainsString('check-circle', $component->defaultIcon());
    }

    public function test_alert_banner_default_icon_warning(): void
    {
        $component = new AlertBanner(type: 'warning');

        $this->assertStringContainsString('exclamation-triangle', $component->defaultIcon());
    }

    public function test_alert_banner_default_icon_danger(): void
    {
        $component = new AlertBanner(type: 'danger');

        $this->assertStringContainsString('x-circle', $component->defaultIcon());
    }

    public function test_alert_banner_non_dismissible(): void
    {
        $component = new AlertBanner(dismissible: false);

        $this->assertFalse($component->dismissible);
    }

    // ========================================================================
    // KpiCard — Edge Cases
    // ========================================================================

    public function test_kpi_card_with_string_values(): void
    {
        $component = new KpiCard(label: 'Budget', actual: '75', target: '100');

        $this->assertEquals(75.0, $component->percentage());
    }

    public function test_kpi_card_with_float_values(): void
    {
        $component = new KpiCard(label: 'Budget', actual: 33.3, target: 100.0);

        $this->assertEquals(33.3, $component->percentage());
    }

    public function test_kpi_card_format_parameter(): void
    {
        $component = new KpiCard(label: 'Budget', actual: 50, target: 100, format: 'currency');

        $this->assertEquals('currency', $component->format);
    }

    public function test_kpi_card_format_defaults_to_null(): void
    {
        $component = new KpiCard(label: 'Budget', actual: 50, target: 100);

        $this->assertNull($component->format);
    }

    public function test_kpi_card_custom_icon(): void
    {
        $component = new KpiCard(label: 'Budget', actual: 50, target: 100, icon: 'heroicon-o-currency-dollar');

        $this->assertEquals('heroicon-o-currency-dollar', $component->icon);
    }

    // ========================================================================
    // TrendCard — Sparkline Path Logic
    // ========================================================================

    public function test_trend_card_sparkline_path_has_correct_start(): void
    {
        $component = new TrendCard(label: 'T', value: 1, data: [10, 20, 30]);
        $path = $component->sparklinePath();

        $this->assertStringStartsWith('M 0,', $path);
    }

    public function test_trend_card_sparkline_path_point_count_matches_data(): void
    {
        $data = [10, 20, 30, 40, 50];
        $component = new TrendCard(label: 'T', value: 1, data: $data);
        $path = $component->sparklinePath();

        // One M point + (count-1) L points
        $lCount = substr_count($path, 'L ');
        $this->assertEquals(count($data) - 1, $lCount);
    }

    #[DataProvider('trendCardColorProvider')]
    public function test_trend_card_sparkline_class_for_each_color(string $color, string $expectedContains): void
    {
        $component = new TrendCard(label: 'T', value: 1, color: $color);

        $this->assertStringContainsString($expectedContains, $component->sparklineClass());
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function trendCardColorProvider(): array
    {
        return [
            'primary' => ['primary', 'primary-500'],
            'success' => ['success', 'green-500'],
            'green' => ['green', 'green-500'],
            'warning' => ['warning', 'yellow-500'],
            'danger' => ['danger', 'red-500'],
            'info' => ['info', 'blue-500'],
            'unknown' => ['unknown', 'gray-500'],
        ];
    }

    public function test_trend_card_default_color(): void
    {
        $component = new TrendCard(label: 'T', value: 1);

        $this->assertEquals('primary', $component->color);
    }

    public function test_trend_card_description_is_optional(): void
    {
        $component = new TrendCard(label: 'T', value: 1);

        $this->assertNull($component->description);
    }

    // ========================================================================
    // SplitLayout — Order Attribute
    // ========================================================================

    public function test_split_layout_reverse_attribute(): void
    {
        $component = new SplitLayout(reverse: true);

        $this->assertTrue($component->reverse);
    }

    // ========================================================================
    // CardGrid — Grid Column Logic
    // ========================================================================

    public function test_card_grid_unknown_cols_uses_default(): void
    {
        $component = new CardGrid(cols: 99);

        $this->assertStringContainsString('lg:grid-cols-3', $component->gridCols());
    }

    // ========================================================================
    // InfoCard — Optional Properties
    // ========================================================================

    public function test_info_card_icon_defaults_to_null(): void
    {
        $component = new InfoCard(heading: 'Details');

        $this->assertNull($component->icon);
    }

    public function test_info_card_items_default_to_empty_array(): void
    {
        $component = new InfoCard(heading: 'Details');

        $this->assertEquals([], $component->items);
    }

    public function test_info_card_with_icon(): void
    {
        $component = new InfoCard(heading: 'Details', icon: 'heroicon-o-information-circle');

        $this->assertEquals('heroicon-o-information-circle', $component->icon);
    }

    // ========================================================================
    // StatusBadge — Icon Support
    // ========================================================================

    public function test_status_badge_icon_defaults_to_null(): void
    {
        $component = new StatusBadge(label: 'Active');

        $this->assertNull($component->icon);
    }

    public function test_status_badge_with_icon(): void
    {
        $component = new StatusBadge(label: 'Active', icon: 'heroicon-o-check');

        $this->assertEquals('heroicon-o-check', $component->icon);
    }

    public function test_status_badge_default_color_is_gray(): void
    {
        $component = new StatusBadge(label: 'Unknown');

        $this->assertEquals('gray', $component->color);
    }

    // ========================================================================
    // QuickAction — Constructor Completeness
    // ========================================================================

    public function test_quick_action_all_params(): void
    {
        $component = new QuickAction(
            icon: 'heroicon-o-link',
            label: 'Copy Link',
            href: '/copy',
            color: 'primary',
        );

        $this->assertEquals('heroicon-o-link', $component->icon);
        $this->assertEquals('Copy Link', $component->label);
        $this->assertEquals('/copy', $component->href);
        $this->assertEquals('primary', $component->color);
    }
}
