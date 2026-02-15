<?php

namespace Aicl\Tests\Unit\Misc;

use Aicl\AiclPlugin;
use Aicl\Filament\Pages\ApiTokens;
use Aicl\Filament\Pages\LogViewer;
use Aicl\Filament\Pages\ManageSettings;
use Aicl\Filament\Pages\NotificationCenter;
use Aicl\Filament\Pages\OpsPanel;
use Aicl\Filament\Pages\QueueDashboard;
use Aicl\Filament\Pages\Search;
use Aicl\Filament\Resources\FailedJobs\FailedJobResource;
use Aicl\Filament\Widgets\GlobalSearchWidget;
use Aicl\Filament\Widgets\PresenceIndicator;
use Aicl\Filament\Widgets\QueueStatsWidget;
use Aicl\Filament\Widgets\RecentFailedJobsWidget;
use Aicl\Http\Middleware\TrackPresenceMiddleware;
use Filament\Contracts\Plugin;
use PHPUnit\Framework\TestCase;

class AiclPluginTest extends TestCase
{
    public function test_implements_plugin_interface(): void
    {
        $this->assertTrue(is_subclass_of(AiclPlugin::class, Plugin::class));
    }

    public function test_get_id(): void
    {
        $plugin = new AiclPlugin;
        $this->assertEquals('aicl', $plugin->getId());
    }

    public function test_has_make_method(): void
    {
        $this->assertTrue(method_exists(AiclPlugin::class, 'make'));

        $reflection = new \ReflectionMethod(AiclPlugin::class, 'make');
        $this->assertTrue($reflection->isStatic());
    }

    public function test_has_get_method(): void
    {
        $this->assertTrue(method_exists(AiclPlugin::class, 'get'));

        $reflection = new \ReflectionMethod(AiclPlugin::class, 'get');
        $this->assertTrue($reflection->isStatic());
    }

    public function test_has_register_method(): void
    {
        $this->assertTrue(method_exists(AiclPlugin::class, 'register'));
    }

    public function test_has_boot_method(): void
    {
        $this->assertTrue(method_exists(AiclPlugin::class, 'boot'));
    }

    public function test_get_resources_includes_failed_job_resource(): void
    {
        $reflection = new \ReflectionMethod(AiclPlugin::class, 'getResources');
        $reflection->setAccessible(true);

        $plugin = new AiclPlugin;
        $resources = $reflection->invoke($plugin);

        $this->assertContains(FailedJobResource::class, $resources);
    }

    public function test_get_pages_includes_all_pages(): void
    {
        $reflection = new \ReflectionMethod(AiclPlugin::class, 'getPages');
        $reflection->setAccessible(true);

        $plugin = new AiclPlugin;
        $pages = $reflection->invoke($plugin);

        $expectedPages = [
            OpsPanel::class,
            QueueDashboard::class,
            LogViewer::class,
            ManageSettings::class,
            NotificationCenter::class,
            Search::class,
            ApiTokens::class,
        ];

        foreach ($expectedPages as $page) {
            $this->assertContains($page, $pages, "Expected {$page} in plugin pages");
        }
    }

    public function test_get_pages_does_not_include_online_users(): void
    {
        $reflection = new \ReflectionMethod(AiclPlugin::class, 'getPages');
        $reflection->setAccessible(true);

        $plugin = new AiclPlugin;
        $pages = $reflection->invoke($plugin);

        // OnlineUsers was removed in Sprint H — consolidated into OpsPanel
        foreach ($pages as $page) {
            $this->assertStringNotContainsString('OnlineUsers', $page);
        }
    }

    public function test_get_widgets_includes_all_widgets(): void
    {
        $reflection = new \ReflectionMethod(AiclPlugin::class, 'getWidgets');
        $reflection->setAccessible(true);

        $plugin = new AiclPlugin;
        $widgets = $reflection->invoke($plugin);

        $expectedWidgets = [
            QueueStatsWidget::class,
            RecentFailedJobsWidget::class,
            GlobalSearchWidget::class,
            PresenceIndicator::class,
        ];

        foreach ($expectedWidgets as $widget) {
            $this->assertContains($widget, $widgets, "Expected {$widget} in plugin widgets");
        }
    }

    // ── Sprint H: Presence Registration ─────────────────────

    public function test_register_method_accepts_panel_parameter(): void
    {
        $reflection = new \ReflectionMethod(AiclPlugin::class, 'register');
        $params = $reflection->getParameters();

        $this->assertCount(1, $params);
        $this->assertSame('panel', $params[0]->getName());
    }

    public function test_register_source_includes_track_presence_middleware(): void
    {
        $reflection = new \ReflectionMethod(AiclPlugin::class, 'register');
        $source = file_get_contents($reflection->getFileName());

        $this->assertStringContainsString(TrackPresenceMiddleware::class, $source);
        $this->assertStringContainsString('authMiddleware', $source);
    }
}
