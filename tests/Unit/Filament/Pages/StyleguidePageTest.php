<?php

namespace Aicl\Tests\Unit\Filament\Pages;

use Aicl\Filament\Pages\Styleguide\ActionComponents;
use Aicl\Filament\Pages\Styleguide\DataDisplayComponents;
use Aicl\Filament\Pages\Styleguide\FeedbackComponents;
use Aicl\Filament\Pages\Styleguide\InteractiveComponents;
use Aicl\Filament\Pages\Styleguide\LayoutComponents;
use Aicl\Filament\Pages\Styleguide\MetricComponents;
use Aicl\Filament\Pages\Styleguide\StyleguideOverview;
use Filament\Pages\Page;
use PHPUnit\Framework\TestCase;

class StyleguidePageTest extends TestCase
{
    // ─── StyleguideOverview ────────────────────────────────────

    public function test_overview_extends_page(): void
    {
        $this->assertTrue(is_subclass_of(StyleguideOverview::class, Page::class));
    }

    public function test_overview_navigation_group(): void
    {
        $reflection = new \ReflectionClass(StyleguideOverview::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertEquals('Styleguide', $defaults['navigationGroup']);
    }

    public function test_overview_navigation_sort(): void
    {
        $reflection = new \ReflectionClass(StyleguideOverview::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertEquals(100, $defaults['navigationSort']);
    }

    public function test_overview_navigation_label(): void
    {
        $reflection = new \ReflectionClass(StyleguideOverview::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertEquals('Overview', $defaults['navigationLabel']);
    }

    public function test_overview_title(): void
    {
        $reflection = new \ReflectionClass(StyleguideOverview::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertEquals('Component Styleguide', $defaults['title']);
    }

    public function test_overview_view(): void
    {
        $reflection = new \ReflectionClass(StyleguideOverview::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertEquals('aicl::filament.pages.styleguide.overview', $defaults['view']);
    }

    // ─── LayoutComponents ──────────────────────────────────────

    public function test_layout_extends_page(): void
    {
        $this->assertTrue(is_subclass_of(LayoutComponents::class, Page::class));
    }

    public function test_layout_navigation_group(): void
    {
        $reflection = new \ReflectionClass(LayoutComponents::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertEquals('Styleguide', $defaults['navigationGroup']);
    }

    public function test_layout_navigation_sort(): void
    {
        $reflection = new \ReflectionClass(LayoutComponents::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertEquals(101, $defaults['navigationSort']);
    }

    public function test_layout_view(): void
    {
        $reflection = new \ReflectionClass(LayoutComponents::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertEquals('aicl::filament.pages.styleguide.layout-components', $defaults['view']);
    }

    // ─── MetricComponents ──────────────────────────────────────

    public function test_metric_extends_page(): void
    {
        $this->assertTrue(is_subclass_of(MetricComponents::class, Page::class));
    }

    public function test_metric_navigation_sort(): void
    {
        $reflection = new \ReflectionClass(MetricComponents::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertEquals(102, $defaults['navigationSort']);
    }

    public function test_metric_view(): void
    {
        $reflection = new \ReflectionClass(MetricComponents::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertEquals('aicl::filament.pages.styleguide.metric-components', $defaults['view']);
    }

    // ─── DataDisplayComponents ─────────────────────────────────

    public function test_data_display_extends_page(): void
    {
        $this->assertTrue(is_subclass_of(DataDisplayComponents::class, Page::class));
    }

    public function test_data_display_navigation_sort(): void
    {
        $reflection = new \ReflectionClass(DataDisplayComponents::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertEquals(103, $defaults['navigationSort']);
    }

    public function test_data_display_view(): void
    {
        $reflection = new \ReflectionClass(DataDisplayComponents::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertEquals('aicl::filament.pages.styleguide.data-display-components', $defaults['view']);
    }

    // ─── ActionComponents ──────────────────────────────────────

    public function test_action_extends_page(): void
    {
        $this->assertTrue(is_subclass_of(ActionComponents::class, Page::class));
    }

    public function test_action_navigation_sort(): void
    {
        $reflection = new \ReflectionClass(ActionComponents::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertEquals(104, $defaults['navigationSort']);
    }

    public function test_action_navigation_label(): void
    {
        $reflection = new \ReflectionClass(ActionComponents::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertEquals('Actions & Utility', $defaults['navigationLabel']);
    }

    public function test_action_view(): void
    {
        $reflection = new \ReflectionClass(ActionComponents::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertEquals('aicl::filament.pages.styleguide.action-components', $defaults['view']);
    }

    // ─── InteractiveComponents ─────────────────────────────────

    public function test_interactive_extends_page(): void
    {
        $this->assertTrue(is_subclass_of(InteractiveComponents::class, Page::class));
    }

    public function test_interactive_navigation_sort(): void
    {
        $reflection = new \ReflectionClass(InteractiveComponents::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertEquals(105, $defaults['navigationSort']);
    }

    public function test_interactive_navigation_label(): void
    {
        $reflection = new \ReflectionClass(InteractiveComponents::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertEquals('Interactive', $defaults['navigationLabel']);
    }

    public function test_interactive_view(): void
    {
        $reflection = new \ReflectionClass(InteractiveComponents::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertEquals('aicl::filament.pages.styleguide.interactive-components', $defaults['view']);
    }

    // ─── FeedbackComponents ─────────────────────────────────────

    public function test_feedback_extends_page(): void
    {
        $this->assertTrue(is_subclass_of(FeedbackComponents::class, Page::class));
    }

    public function test_feedback_navigation_sort(): void
    {
        $reflection = new \ReflectionClass(FeedbackComponents::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertEquals(106, $defaults['navigationSort']);
    }

    public function test_feedback_navigation_label(): void
    {
        $reflection = new \ReflectionClass(FeedbackComponents::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertEquals('Feedback', $defaults['navigationLabel']);
    }

    public function test_feedback_view(): void
    {
        $reflection = new \ReflectionClass(FeedbackComponents::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertEquals('aicl::filament.pages.styleguide.feedback-components', $defaults['view']);
    }

    // ─── All pages share Styleguide group ──────────────────────

    public function test_all_styleguide_pages_in_same_group(): void
    {
        $pages = [
            StyleguideOverview::class,
            LayoutComponents::class,
            MetricComponents::class,
            DataDisplayComponents::class,
            ActionComponents::class,
            InteractiveComponents::class,
            FeedbackComponents::class,
        ];

        foreach ($pages as $page) {
            $reflection = new \ReflectionClass($page);
            $defaults = $reflection->getDefaultProperties();
            $this->assertEquals('Styleguide', $defaults['navigationGroup'], "Expected {$page} to be in Styleguide group");
        }
    }

    public function test_all_styleguide_pages_have_sequential_sort(): void
    {
        $pages = [
            StyleguideOverview::class => 100,
            LayoutComponents::class => 101,
            MetricComponents::class => 102,
            DataDisplayComponents::class => 103,
            ActionComponents::class => 104,
            InteractiveComponents::class => 105,
            FeedbackComponents::class => 106,
        ];

        foreach ($pages as $page => $expectedSort) {
            $reflection = new \ReflectionClass($page);
            $defaults = $reflection->getDefaultProperties();
            $this->assertEquals($expectedSort, $defaults['navigationSort'], "Expected {$page} sort to be {$expectedSort}");
        }
    }
}
