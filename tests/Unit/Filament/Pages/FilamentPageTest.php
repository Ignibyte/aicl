<?php

namespace Aicl\Tests\Unit\Filament\Pages;

use Aicl\Filament\Pages\ApiTokens;
use Aicl\Filament\Pages\LogViewer;
use Aicl\Filament\Pages\ManageSettings;
use Aicl\Filament\Pages\NotificationCenter;
use Aicl\Filament\Pages\QueueDashboard;
use Aicl\Filament\Pages\Search;
use Filament\Pages\Page;
use PHPUnit\Framework\TestCase;

class FilamentPageTest extends TestCase
{
    // ─── QueueDashboard ─────────────────────────────────────

    public function test_queue_dashboard_extends_page(): void
    {
        $this->assertTrue(is_subclass_of(QueueDashboard::class, Page::class));
    }

    public function test_queue_dashboard_slug(): void
    {
        $reflection = new \ReflectionClass(QueueDashboard::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertEquals('queue-dashboard', $defaults['slug']);
    }

    public function test_queue_dashboard_navigation_group(): void
    {
        $reflection = new \ReflectionClass(QueueDashboard::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertEquals('System', $defaults['navigationGroup']);
    }

    // ─── LogViewer ──────────────────────────────────────────

    public function test_log_viewer_extends_page(): void
    {
        $this->assertTrue(is_subclass_of(LogViewer::class, Page::class));
    }

    public function test_log_viewer_slug(): void
    {
        $reflection = new \ReflectionClass(LogViewer::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertEquals('log-viewer', $defaults['slug']);
    }

    public function test_log_viewer_default_properties(): void
    {
        $reflection = new \ReflectionClass(LogViewer::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertNull($defaults['selectedFile']);
        $this->assertNull($defaults['levelFilter']);
        $this->assertNull($defaults['search']);
        $this->assertFalse($defaults['autoRefresh']);
        $this->assertEquals(100, $defaults['limit']);
    }

    // ─── ManageSettings ─────────────────────────────────────

    public function test_manage_settings_extends_page(): void
    {
        $this->assertTrue(is_subclass_of(ManageSettings::class, Page::class));
    }

    public function test_manage_settings_slug(): void
    {
        $reflection = new \ReflectionClass(ManageSettings::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertEquals('settings', $defaults['slug']);
    }

    // ─── Search ─────────────────────────────────────────────

    public function test_search_extends_page(): void
    {
        $this->assertTrue(is_subclass_of(Search::class, Page::class));
    }

    public function test_search_slug(): void
    {
        $reflection = new \ReflectionClass(Search::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertEquals('search', $defaults['slug']);
    }

    public function test_search_default_properties(): void
    {
        $reflection = new \ReflectionClass(Search::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertEquals('', $defaults['query']);
        $this->assertNull($defaults['entityType']);
    }

    public function test_search_not_registered_in_navigation(): void
    {
        $reflection = new \ReflectionClass(Search::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertFalse($defaults['shouldRegisterNavigation']);
    }

    // ─── ApiTokens ──────────────────────────────────────────

    public function test_api_tokens_extends_page(): void
    {
        $this->assertTrue(is_subclass_of(ApiTokens::class, Page::class));
    }

    public function test_api_tokens_slug(): void
    {
        $reflection = new \ReflectionClass(ApiTokens::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertEquals('api-tokens', $defaults['slug']);
    }

    public function test_api_tokens_default_properties(): void
    {
        $reflection = new \ReflectionClass(ApiTokens::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertEquals('', $defaults['newTokenName']);
        $this->assertNull($defaults['createdToken']);
    }

    // ─── NotificationCenter ─────────────────────────────────

    public function test_notification_center_extends_page(): void
    {
        $this->assertTrue(is_subclass_of(NotificationCenter::class, Page::class));
    }

    public function test_notification_center_slug(): void
    {
        $reflection = new \ReflectionClass(NotificationCenter::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertEquals('notifications', $defaults['slug']);
    }

    public function test_notification_center_default_filter(): void
    {
        $reflection = new \ReflectionClass(NotificationCenter::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertEquals('all', $defaults['filter']);
    }

    public function test_notification_center_not_in_navigation(): void
    {
        $reflection = new \ReflectionClass(NotificationCenter::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertFalse($defaults['shouldRegisterNavigation']);
    }
}
