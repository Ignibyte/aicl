<?php

namespace Aicl\Tests\Unit\Filament;

use Aicl\AiclPlugin;
use PHPUnit\Framework\TestCase;

class NavigationSwitcherTest extends TestCase
{
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

        /** @phpstan-ignore-next-line */
        $this->assertStringContainsString('localStorage.getItem', $content);
        /** @phpstan-ignore-next-line */
        $this->assertStringContainsString('aicl_nav_layout', $content);
        /** @phpstan-ignore-next-line */
        $this->assertStringContainsString('data-nav-mode', $content);
    }

    public function test_switcher_init_script_defaults_to_sidebar(): void
    {
        $viewPath = __DIR__.'/../../../resources/views/components/navigation-switcher-init.blade.php';
        $content = file_get_contents($viewPath);

        // When localStorage has no value, default should be 'sidebar'
        /** @phpstan-ignore-next-line */
        $this->assertStringContainsString("|| 'sidebar'", $content);
    }

    public function test_switcher_toggle_uses_alpine_component(): void
    {
        $viewPath = __DIR__.'/../../../resources/views/components/navigation-switcher-toggle.blade.php';
        $content = file_get_contents($viewPath);

        /** @phpstan-ignore-next-line */
        $this->assertStringContainsString('x-data="navigationSwitcher()"', $content);
        /** @phpstan-ignore-next-line */
        $this->assertStringContainsString('x-on:click="toggle()"', $content);
    }

    public function test_switcher_toggle_uses_distinct_icons(): void
    {
        $viewPath = __DIR__.'/../../../resources/views/components/navigation-switcher-toggle.blade.php';
        $content = file_get_contents($viewPath);

        /** @phpstan-ignore-next-line */
        $this->assertStringContainsString('x-heroicon-o-view-columns', $content);
        /** @phpstan-ignore-next-line */
        $this->assertStringContainsString('x-heroicon-o-arrows-right-left', $content);
        // Must NOT use bars-3 (conflicts with Filament's hamburger menu)
        /** @phpstan-ignore-next-line */
        $this->assertStringNotContainsString('heroicon-o-bars-3', $content);
    }

    public function test_js_file_contains_navigation_switcher_function(): void
    {
        $jsPath = __DIR__.'/../../../resources/js/aicl-widgets.js';
        $content = file_get_contents($jsPath);

        /** @phpstan-ignore-next-line */
        $this->assertStringContainsString('window.navigationSwitcher', $content);
    }

    public function test_js_navigation_switcher_reads_localstorage(): void
    {
        $jsPath = __DIR__.'/../../../resources/js/aicl-widgets.js';
        $content = file_get_contents($jsPath);

        /** @phpstan-ignore-next-line */
        $this->assertStringContainsString("localStorage.getItem('aicl_nav_layout')", $content);
        /** @phpstan-ignore-next-line */
        $this->assertStringContainsString("localStorage.setItem('aicl_nav_layout'", $content);
    }

    public function test_js_navigation_switcher_defaults_to_sidebar(): void
    {
        $jsPath = __DIR__.'/../../../resources/js/aicl-widgets.js';
        $content = file_get_contents($jsPath);

        /** @phpstan-ignore-next-line */
        $this->assertStringContainsString("|| 'sidebar'", $content);
    }

    public function test_js_navigation_switcher_does_not_toggle_body_class(): void
    {
        $jsPath = __DIR__.'/../../../resources/js/aicl-widgets.js';
        $content = file_get_contents($jsPath);

        // The JS must NOT toggle fi-body-has-top-navigation — CSS overrides
        // in theme.css handle visibility via data-nav-mode attribute instead.
        /** @phpstan-ignore-next-line */
        $this->assertStringContainsString('fi-body-has-top-navigation', $content);
        /** @phpstan-ignore-next-line */
        $this->assertStringNotContainsString('classList.add', $content);
        /** @phpstan-ignore-next-line */
        $this->assertStringNotContainsString('classList.remove', $content);
    }

    public function test_js_navigation_switcher_manages_sidebar_store(): void
    {
        $jsPath = __DIR__.'/../../../resources/js/aicl-widgets.js';
        $content = file_get_contents($jsPath);

        // Should close sidebar when switching to topbar
        /** @phpstan-ignore-next-line */
        $this->assertStringContainsString("Alpine.store('sidebar').close()", $content);
        // Should open sidebar when switching back
        /** @phpstan-ignore-next-line */
        $this->assertStringContainsString("Alpine.store('sidebar').open()", $content);
    }

    public function test_plugin_always_enables_top_navigation(): void
    {
        /** @phpstan-ignore-next-line */
        $source = file_get_contents((new \ReflectionClass(AiclPlugin::class))->getFileName());

        /** @phpstan-ignore-next-line */
        $this->assertStringContainsString('topNavigation()', $source);
        // Should NOT have config-dependent conditional
        /** @phpstan-ignore-next-line */
        $this->assertStringNotContainsString('navigation_layout', $source);
    }

    public function test_plugin_always_registers_switcher_render_hooks(): void
    {
        /** @phpstan-ignore-next-line */
        $source = file_get_contents((new \ReflectionClass(AiclPlugin::class))->getFileName());

        /** @phpstan-ignore-next-line */
        $this->assertStringContainsString('navigation-switcher-init', $source);
        /** @phpstan-ignore-next-line */
        $this->assertStringContainsString('navigation-switcher-toggle', $source);
        /** @phpstan-ignore-next-line */
        $this->assertStringContainsString('HEAD_END', $source);
        /** @phpstan-ignore-next-line */
        $this->assertStringContainsString('USER_MENU_BEFORE', $source);
    }

    public function test_config_does_not_contain_navigation_layout_key(): void
    {
        $config = require __DIR__.'/../../../config/aicl.php';

        $this->assertArrayHasKey('theme', $config);
        $this->assertArrayNotHasKey('navigation_layout', $config['theme']);
    }
}
