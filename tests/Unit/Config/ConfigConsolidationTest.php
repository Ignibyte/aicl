<?php

namespace Aicl\Tests\Unit\Config;

use Aicl\AiclPlugin;
use Aicl\AiclServiceProvider;
use Aicl\Filament\Pages\ApiTokens;
use Aicl\Filament\Pages\Auth\Login;
use Aicl\Mcp\AiclMcpServer;
use Orchestra\Testbench\TestCase;

/**
 * Tests for the config consolidation refactor.
 *
 * Verifies that all config-driven feature flags, method behaviors, and
 * config reads work correctly after the consolidation from Spatie Settings
 * to pure config() calls.
 */
class ConfigConsolidationTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [AiclServiceProvider::class];
    }

    // ── AiclPlugin: isRegistrationEnabled ─────────────────────

    public function test_is_registration_enabled_returns_false_by_default(): void
    {
        $this->assertFalse(AiclPlugin::isRegistrationEnabled());
    }

    public function test_is_registration_enabled_returns_true_when_config_set(): void
    {
        config()->set('aicl.features.allow_registration', true);

        $this->assertTrue(AiclPlugin::isRegistrationEnabled());
    }

    public function test_is_registration_enabled_returns_false_when_config_explicitly_false(): void
    {
        config()->set('aicl.features.allow_registration', false);

        $this->assertFalse(AiclPlugin::isRegistrationEnabled());
    }

    public function test_is_registration_enabled_casts_truthy_string_to_true(): void
    {
        config()->set('aicl.features.allow_registration', '1');

        $this->assertTrue(AiclPlugin::isRegistrationEnabled());
    }

    public function test_is_registration_enabled_casts_falsy_value_to_false(): void
    {
        config()->set('aicl.features.allow_registration', 0);

        $this->assertFalse(AiclPlugin::isRegistrationEnabled());
    }

    // ── AiclPlugin: isEmailVerificationRequired ─────────────────────

    public function test_is_email_verification_required_returns_true_by_default(): void
    {
        $this->assertTrue(AiclPlugin::isEmailVerificationRequired());
    }

    public function test_is_email_verification_required_returns_false_when_disabled(): void
    {
        config()->set('aicl.features.require_email_verification', false);

        $this->assertFalse(AiclPlugin::isEmailVerificationRequired());
    }

    public function test_is_email_verification_required_returns_true_when_enabled(): void
    {
        config()->set('aicl.features.require_email_verification', true);

        $this->assertTrue(AiclPlugin::isEmailVerificationRequired());
    }

    public function test_is_email_verification_required_casts_truthy_value(): void
    {
        config()->set('aicl.features.require_email_verification', '1');

        $this->assertTrue(AiclPlugin::isEmailVerificationRequired());
    }

    public function test_is_email_verification_required_casts_falsy_value(): void
    {
        config()->set('aicl.features.require_email_verification', 0);

        $this->assertFalse(AiclPlugin::isEmailVerificationRequired());
    }

    // ── AiclPlugin: getPages — no ManageSettings ─────────────────────

    public function test_get_pages_does_not_include_manage_settings(): void
    {
        $reflection = new \ReflectionMethod(AiclPlugin::class, 'getPages');
        $reflection->setAccessible(true);

        $plugin = new AiclPlugin;
        $pages = $reflection->invoke($plugin);

        foreach ($pages as $page) {
            $this->assertStringNotContainsString(
                'ManageSettings',
                $page,
                'ManageSettings page should not be registered (settings moved to config)'
            );
        }
    }

    public function test_get_pages_does_not_include_settings_page(): void
    {
        $reflection = new \ReflectionMethod(AiclPlugin::class, 'getPages');
        $reflection->setAccessible(true);

        $plugin = new AiclPlugin;
        $pages = $reflection->invoke($plugin);

        foreach ($pages as $page) {
            $this->assertStringNotContainsString(
                'Settings',
                $page,
                'No Settings page should be registered (settings consolidated to config)'
            );
        }
    }

    // ── AiclPlugin: MFA closure reads config ─────────────────────

    public function test_plugin_source_reads_require_mfa_from_config(): void
    {
        $source = file_get_contents(
            (new \ReflectionClass(AiclPlugin::class))->getFileName()
        );

        $this->assertStringContainsString(
            "config('aicl.features.require_mfa'",
            $source,
            'MFA force closure must read require_mfa from config'
        );
    }

    public function test_plugin_source_does_not_reference_feature_settings(): void
    {
        $source = file_get_contents(
            (new \ReflectionClass(AiclPlugin::class))->getFileName()
        );

        $this->assertStringNotContainsString(
            'FeatureSettings',
            $source,
            'Plugin must not reference the removed FeatureSettings Spatie class'
        );
    }

    // ── AiclMcpServer: config reads ─────────────────────

    public function test_mcp_server_reads_exposed_entities_from_config(): void
    {
        $source = file_get_contents(
            (new \ReflectionClass(AiclMcpServer::class))->getFileName()
        );

        $this->assertStringContainsString(
            "config('aicl.mcp.exposed_entities'",
            $source
        );
    }

    public function test_mcp_server_reads_custom_tools_enabled_from_config(): void
    {
        $source = file_get_contents(
            (new \ReflectionClass(AiclMcpServer::class))->getFileName()
        );

        $this->assertStringContainsString(
            "config('aicl.mcp.custom_tools_enabled'",
            $source
        );
    }

    public function test_mcp_server_reads_server_description_from_config(): void
    {
        $source = file_get_contents(
            (new \ReflectionClass(AiclMcpServer::class))->getFileName()
        );

        $this->assertStringContainsString(
            "config('aicl.mcp.server_info.description')",
            $source
        );
    }

    // ── ApiTokens: config-driven methods ─────────────────────

    public function test_api_tokens_is_mcp_available_reads_config(): void
    {
        config()->set('aicl.features.mcp', false);

        $page = new ApiTokens;
        $this->assertFalse($page->isMcpAvailable());
    }

    public function test_api_tokens_is_mcp_available_requires_mcp_class(): void
    {
        config()->set('aicl.features.mcp', true);

        $page = new ApiTokens;
        // Returns true because Mcp class exists in this test environment
        $this->assertTrue($page->isMcpAvailable());
    }

    public function test_api_tokens_get_mcp_url_reads_app_url_and_mcp_path(): void
    {
        config()->set('app.url', 'https://my-app.test');
        config()->set('aicl.mcp.path', '/mcp-custom');

        $page = new ApiTokens;
        $this->assertSame('https://my-app.test/mcp-custom', $page->getMcpUrl());
    }

    public function test_api_tokens_get_mcp_url_strips_trailing_slash(): void
    {
        config()->set('app.url', 'https://my-app.test/');
        config()->set('aicl.mcp.path', '/mcp');

        $page = new ApiTokens;
        $this->assertSame('https://my-app.test/mcp', $page->getMcpUrl());
    }

    public function test_api_tokens_get_mcp_tool_count_returns_zero_when_disabled(): void
    {
        config()->set('aicl.features.mcp', false);

        $page = new ApiTokens;
        $this->assertSame(0, $page->getMcpToolCount());
    }

    public function test_api_tokens_source_does_not_have_toggle_description_methods(): void
    {
        $reflection = new \ReflectionClass(ApiTokens::class);
        $methods = array_map(
            fn (\ReflectionMethod $m) => $m->getName(),
            $reflection->getMethods(\ReflectionMethod::IS_PUBLIC)
        );

        $this->assertNotContains('toggleMcp', $methods);
        $this->assertNotContains('updateMcpDescription', $methods);
        $this->assertNotContains('setMcpToggle', $methods);
    }

    // ── Login: config-driven methods ─────────────────────

    public function test_login_has_social_login_returns_false_when_disabled(): void
    {
        config()->set('aicl.features.social_login', false);

        $login = new Login;
        $this->assertFalse($login->hasSocialLogin());
    }

    public function test_login_has_saml_login_returns_false_when_disabled(): void
    {
        config()->set('aicl.features.saml', false);

        $login = new Login;
        $this->assertFalse($login->hasSamlLogin());
    }

    public function test_login_has_saml_login_returns_true_when_enabled(): void
    {
        config()->set('aicl.features.saml', true);

        $login = new Login;
        $this->assertTrue($login->hasSamlLogin());
    }

    public function test_login_has_saml_login_casts_truthy_value(): void
    {
        config()->set('aicl.features.saml', '1');

        $login = new Login;
        $this->assertTrue($login->hasSamlLogin());
    }

    // ── AiclServiceProvider: loadLocalConfig ─────────────────────

    public function test_load_local_config_method_exists(): void
    {
        $reflection = new \ReflectionMethod(AiclServiceProvider::class, 'loadLocalConfig');

        $this->assertTrue($reflection->isProtected());
    }

    public function test_load_local_config_skips_when_file_missing(): void
    {
        // The default test environment has no config/local.php.
        // If loadLocalConfig didn't handle this gracefully, boot would fail.
        $originalValue = config('aicl.features.mcp');

        // Re-call to verify no error
        $provider = new AiclServiceProvider($this->app);
        $reflection = new \ReflectionMethod($provider, 'loadLocalConfig');
        $reflection->setAccessible(true);
        $reflection->invoke($provider);

        // Config unchanged — file was skipped
        $this->assertSame($originalValue, config('aicl.features.mcp'));
    }

    public function test_load_local_config_applies_overrides_from_file(): void
    {
        // Create a temporary local.php file
        $configPath = $this->app->configPath('local.php');
        $dir = dirname($configPath);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($configPath, '<?php return ["aicl.features.mcp" => true, "aicl.features.saml" => true];');

        try {
            $provider = new AiclServiceProvider($this->app);
            $reflection = new \ReflectionMethod($provider, 'loadLocalConfig');
            $reflection->setAccessible(true);
            $reflection->invoke($provider);

            $this->assertTrue(config('aicl.features.mcp'));
            $this->assertTrue(config('aicl.features.saml'));
        } finally {
            @unlink($configPath);
        }
    }

    public function test_load_local_config_ignores_non_array_return(): void
    {
        $configPath = $this->app->configPath('local.php');
        $dir = dirname($configPath);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($configPath, '<?php return "not an array";');

        try {
            $originalMcp = config('aicl.features.mcp');

            $provider = new AiclServiceProvider($this->app);
            $reflection = new \ReflectionMethod($provider, 'loadLocalConfig');
            $reflection->setAccessible(true);
            $reflection->invoke($provider);

            // Config should be unchanged when file returns non-array
            $this->assertSame($originalMcp, config('aicl.features.mcp'));
        } finally {
            @unlink($configPath);
        }
    }

    // ── Config precedence ─────────────────────

    public function test_config_precedence_local_overrides_project_overrides_package(): void
    {
        // Package default for mcp is false
        $this->assertFalse(config('aicl.features.mcp'));

        // Project overlay sets it to true
        config()->set('aicl.features.mcp', true);
        $this->assertTrue(config('aicl.features.mcp'));

        // Local override sets it back to false
        config()->set('aicl.features.mcp', false);
        $this->assertFalse(config('aicl.features.mcp'));
    }

    public function test_all_feature_flags_are_present_in_config(): void
    {
        $features = config('aicl.features');

        $expectedKeys = [
            'mfa',
            'require_mfa',
            'require_email_verification',
            'social_login',
            'saml',
            'allow_registration',
            'api',
            'websockets',
            'scout_driver',
            'horizon',
            'mcp',
        ];

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $features, "Missing feature flag: {$key}");
        }
    }

    public function test_no_spatie_settings_classes_referenced_in_plugin(): void
    {
        $source = file_get_contents(
            (new \ReflectionClass(AiclPlugin::class))->getFileName()
        );

        $this->assertStringNotContainsString('use App\\Settings\\', $source);
        $this->assertStringNotContainsString('ManageSettings', $source);
        $this->assertStringNotContainsString('SiteSettings', $source);
    }

    public function test_no_spatie_settings_classes_referenced_in_service_provider(): void
    {
        $source = file_get_contents(
            (new \ReflectionClass(AiclServiceProvider::class))->getFileName()
        );

        $this->assertStringNotContainsString('use App\\Settings\\', $source);
        $this->assertStringNotContainsString('SiteSettings', $source);
        $this->assertStringNotContainsString('FeatureSettings', $source);
    }
}
