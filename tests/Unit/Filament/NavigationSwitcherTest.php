<?php

namespace Aicl\Tests\Unit\Filament;

use Aicl\AiclPlugin;
use PHPUnit\Framework\TestCase;

class NavigationSwitcherTest extends TestCase
{
    public function test_navigation_layout_config_key_exists(): void
    {
        $config = require __DIR__.'/../../../config/aicl.php';

        $this->assertArrayHasKey('theme', $config);
        $this->assertArrayHasKey('navigation_layout', $config['theme']);
    }

    public function test_navigation_layout_env_default_is_sidebar(): void
    {
        // Verify the config file uses env() with 'sidebar' as the default
        $configSource = file_get_contents(__DIR__.'/../../../config/aicl.php');

        $this->assertStringContainsString("env('AICL_NAV_LAYOUT', 'sidebar')", $configSource);
    }

    public function test_switcher_init_view_exists(): void
    {
        $viewPath = __DIR__.'/../../../resources/views/components/navigation-switcher-init.blade.php';

        $this->assertFileExists($viewPath);
    }

    public function test_switcher_toggle_view_exists(): void
    {
        $viewPath = __DIR__.'/../../../resources/views/components/navigation-switcher-toggle.blade.php';

        $this->assertFileExists($viewPath);
    }

    public function test_switcher_init_script_reads_localstorage(): void
    {
        $viewPath = __DIR__.'/../../../resources/views/components/navigation-switcher-init.blade.php';
        $content = file_get_contents($viewPath);

        $this->assertStringContainsString('localStorage.getItem', $content);
        $this->assertStringContainsString('aicl_nav_layout', $content);
        $this->assertStringContainsString('data-nav-mode', $content);
    }

    public function test_switcher_init_script_defaults_to_sidebar(): void
    {
        $viewPath = __DIR__.'/../../../resources/views/components/navigation-switcher-init.blade.php';
        $content = file_get_contents($viewPath);

        // When localStorage has no value, default should be 'sidebar'
        $this->assertStringContainsString("|| 'sidebar'", $content);
    }

    public function test_switcher_toggle_uses_alpine_component(): void
    {
        $viewPath = __DIR__.'/../../../resources/views/components/navigation-switcher-toggle.blade.php';
        $content = file_get_contents($viewPath);

        $this->assertStringContainsString('x-data="navigationSwitcher()"', $content);
        $this->assertStringContainsString('x-on:click="toggle()"', $content);
    }

    public function test_switcher_toggle_uses_fontawesome_icons(): void
    {
        $viewPath = __DIR__.'/../../../resources/views/components/navigation-switcher-toggle.blade.php';
        $content = file_get_contents($viewPath);

        $this->assertStringContainsString('x-fas-table-columns', $content);
        $this->assertStringContainsString('x-fas-bars', $content);
    }

    public function test_js_file_contains_navigation_switcher_function(): void
    {
        $jsPath = __DIR__.'/../../../resources/js/aicl-widgets.js';
        $content = file_get_contents($jsPath);

        $this->assertStringContainsString('window.navigationSwitcher', $content);
    }

    public function test_js_navigation_switcher_reads_localstorage(): void
    {
        $jsPath = __DIR__.'/../../../resources/js/aicl-widgets.js';
        $content = file_get_contents($jsPath);

        $this->assertStringContainsString("localStorage.getItem('aicl_nav_layout')", $content);
        $this->assertStringContainsString("localStorage.setItem('aicl_nav_layout'", $content);
    }

    public function test_js_navigation_switcher_defaults_to_sidebar(): void
    {
        $jsPath = __DIR__.'/../../../resources/js/aicl-widgets.js';
        $content = file_get_contents($jsPath);

        $this->assertStringContainsString("|| 'sidebar'", $content);
    }

    public function test_js_navigation_switcher_does_not_toggle_body_class(): void
    {
        $jsPath = __DIR__.'/../../../resources/js/aicl-widgets.js';
        $content = file_get_contents($jsPath);

        // The JS must NOT toggle fi-body-has-top-navigation — CSS overrides
        // in theme.css handle visibility via data-nav-mode attribute instead.
        $this->assertStringContainsString('fi-body-has-top-navigation', $content);
        $this->assertStringNotContainsString('classList.add', $content);
        $this->assertStringNotContainsString('classList.remove', $content);
    }

    public function test_js_navigation_switcher_manages_sidebar_store(): void
    {
        $jsPath = __DIR__.'/../../../resources/js/aicl-widgets.js';
        $content = file_get_contents($jsPath);

        // Should close sidebar when switching to topbar
        $this->assertStringContainsString("Alpine.store('sidebar').close()", $content);
        // Should open sidebar when switching back
        $this->assertStringContainsString("Alpine.store('sidebar').open()", $content);
    }

    public function test_plugin_register_sets_top_navigation_for_topbar_mode(): void
    {
        $source = file_get_contents((new \ReflectionClass(AiclPlugin::class))->getFileName());

        // Plugin should check for topbar and switchable modes
        $this->assertStringContainsString("['topbar', 'switchable']", $source);
        $this->assertStringContainsString('topNavigation()', $source);
    }

    public function test_plugin_boot_registers_render_hooks_for_switchable(): void
    {
        $source = file_get_contents((new \ReflectionClass(AiclPlugin::class))->getFileName());

        $this->assertStringContainsString('navigation-switcher-init', $source);
        $this->assertStringContainsString('navigation-switcher-toggle', $source);
        $this->assertStringContainsString('HEAD_END', $source);
        $this->assertStringContainsString('USER_MENU_BEFORE', $source);
    }
}
