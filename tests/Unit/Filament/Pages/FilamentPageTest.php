<?php

namespace Aicl\Tests\Unit\Filament\Pages;

use Aicl\Filament\Pages\ActivityLog;
use Aicl\Filament\Pages\ApiTokens;
use Aicl\Filament\Pages\NotificationCenter;
use Aicl\Filament\Pages\OperationsManager;
use Aicl\Filament\Pages\Search;
use Filament\Pages\Page;
use PHPUnit\Framework\TestCase;

class FilamentPageTest extends TestCase
{
    // ─── OperationsManager ─────────────────────────────────────

    public function test_operations_manager_extends_page(): void
    {
        $this->assertTrue((new \ReflectionClass(OperationsManager::class))->isSubclassOf(Page::class));
    }

    public function test_operations_manager_slug(): void
    {
        $reflection = new \ReflectionClass(OperationsManager::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertEquals('operations-manager', $defaults['slug']);
    }

    public function test_operations_manager_navigation_group(): void
    {
        $reflection = new \ReflectionClass(OperationsManager::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertEquals('System', $defaults['navigationGroup']);
    }

    // ─── ActivityLog ─────────────────────────────────────────

    public function test_activity_log_extends_page(): void
    {
        $this->assertTrue((new \ReflectionClass(ActivityLog::class))->isSubclassOf(Page::class));
    }

    public function test_activity_log_slug(): void
    {
        $reflection = new \ReflectionClass(ActivityLog::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertEquals('activity-log', $defaults['slug']);
    }

    public function test_activity_log_default_properties(): void
    {
        $reflection = new \ReflectionClass(ActivityLog::class);
        $defaults = $reflection->getDefaultProperties();

        $this->assertNull($defaults['selectedFile']);
        $this->assertNull($defaults['levelFilter']);
        $this->assertNull($defaults['search']);
        $this->assertFalse($defaults['liveMode']);
        $this->assertEquals(100, $defaults['limit']);
        $this->assertEquals('app-logs', $defaults['activeTab']);
    }

    // ─── Search ─────────────────────────────────────────────

    public function test_search_extends_page(): void
    {
        $this->assertTrue((new \ReflectionClass(Search::class))->isSubclassOf(Page::class));
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
        $this->assertSame('', $defaults['entityType']);
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
        $this->assertTrue((new \ReflectionClass(ApiTokens::class))->isSubclassOf(Page::class));
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
        $this->assertTrue((new \ReflectionClass(NotificationCenter::class))->isSubclassOf(Page::class));
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
