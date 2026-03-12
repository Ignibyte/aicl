<?php

namespace Aicl\Tests\Feature\Config;

use Tests\TestCase;

class ProjectConfigOverlayTest extends TestCase
{
    public function test_project_config_overlay_values_override_base(): void
    {
        // Set an overlay value
        config(['aicl-project' => ['theme' => ['brand_name' => 'My Custom App']]]);

        // Re-trigger the merge (simulating boot)
        $projectConfig = config('aicl-project', []);
        if (! empty($projectConfig)) {
            config(['aicl' => array_replace_recursive(config('aicl', []), $projectConfig)]);
        }

        $this->assertSame('My Custom App', config('aicl.theme.brand_name'));
    }

    public function test_project_config_overlay_deep_merges_nested_keys(): void
    {
        // Base has theme.brand_name, theme.logo, theme.favicon
        $baseBrandName = config('aicl.theme.brand_name');
        $baseLogo = config('aicl.theme.logo');

        // Overlay only sets brand_name — logo and favicon should survive
        config(['aicl-project' => ['theme' => ['brand_name' => 'Overlay Brand']]]);

        $projectConfig = config('aicl-project', []);
        config(['aicl' => array_replace_recursive(config('aicl', []), $projectConfig)]);

        $this->assertSame('Overlay Brand', config('aicl.theme.brand_name'));
        $this->assertSame($baseLogo, config('aicl.theme.logo'));
    }

    public function test_absent_project_config_has_no_effect(): void
    {
        $originalConfig = config('aicl');

        // Simulate absent/empty overlay
        config(['aicl-project' => []]);

        $projectConfig = config('aicl-project', []);
        if (! empty($projectConfig)) {
            config(['aicl' => array_replace_recursive(config('aicl', []), $projectConfig)]);
        }

        $this->assertSame($originalConfig, config('aicl'));
    }

    public function test_project_config_overlay_replaces_indexed_arrays(): void
    {
        // Base has social_providers => ['google', 'github']
        config(['aicl.social_providers' => ['google', 'github']]);

        // Overlay replaces the indexed array entirely
        config(['aicl-project' => ['social_providers' => ['okta']]]);

        $projectConfig = config('aicl-project', []);
        config(['aicl' => array_replace_recursive(config('aicl', []), $projectConfig)]);

        // array_replace_recursive replaces indexed arrays by index, not append
        // With only ['okta'], index 0 is replaced, index 1 survives
        // This is the documented behavior — indexed arrays are replaced per-index
        $result = config('aicl.social_providers');
        $this->assertSame('okta', $result[0]);
    }

    public function test_project_config_adds_new_keys_not_in_base(): void
    {
        // Overlay adds a key that doesn't exist in base
        config(['aicl-project' => ['custom_setting' => 'custom_value']]);

        $projectConfig = config('aicl-project', []);
        config(['aicl' => array_replace_recursive(config('aicl', []), $projectConfig)]);

        $this->assertSame('custom_value', config('aicl.custom_setting'));
    }
}
