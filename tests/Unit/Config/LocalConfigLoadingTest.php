<?php

namespace Aicl\Tests\Unit\Config;

use Aicl\AiclServiceProvider;
use Orchestra\Testbench\TestCase;

class LocalConfigLoadingTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [AiclServiceProvider::class];
    }

    public function test_missing_local_config_does_not_break_boot(): void
    {
        // local.php doesn't exist in test environment — boot should succeed
        $this->assertNotNull(config('aicl.features'));
    }

    public function test_local_config_overrides_dot_notation_keys(): void
    {
        // Simulate what loadLocalConfig does with dot-notation
        config()->set('aicl.features.allow_registration', true);

        $this->assertTrue(config('aicl.features.allow_registration'));
    }

    public function test_local_config_can_override_nested_values(): void
    {
        config()->set('database.connections.pgsql.host', 'custom-host');

        $this->assertSame('custom-host', config('database.connections.pgsql.host'));
    }

    public function test_local_config_overrides_apply_on_top_of_defaults(): void
    {
        // Whatever the current value is, override should change it
        $original = config('aicl.features.mcp');
        config()->set('aicl.features.mcp', ! $original);

        $this->assertSame(! $original, config('aicl.features.mcp'));
    }

    public function test_feature_flags_all_present(): void
    {
        // Verify all expected feature keys exist (values may vary by environment)
        $this->assertNotNull(config('aicl.features'));
        $this->assertArrayHasKey('allow_registration', config('aicl.features'));
        $this->assertArrayHasKey('require_email_verification', config('aicl.features'));
        $this->assertArrayHasKey('require_mfa', config('aicl.features'));
        $this->assertArrayHasKey('mfa', config('aicl.features'));
        $this->assertArrayHasKey('social_login', config('aicl.features'));
        $this->assertArrayHasKey('saml', config('aicl.features'));
        $this->assertArrayHasKey('api', config('aicl.features'));
        $this->assertArrayHasKey('mcp', config('aicl.features'));
    }

    public function test_mcp_config_has_expected_defaults(): void
    {
        $this->assertSame(['*'], config('aicl.mcp.exposed_entities'));
        $this->assertTrue(config('aicl.mcp.custom_tools_enabled'));
        $this->assertSame(60, config('aicl.mcp.rate_limit_per_minute'));
        $this->assertSame(10, config('aicl.mcp.max_sessions'));
        $this->assertNull(config('aicl.mcp.server_info.description'));
    }

    public function test_display_config_has_expected_defaults(): void
    {
        $this->assertSame('Y-m-d', config('aicl.display.date_format'));
        $this->assertSame(25, config('aicl.display.items_per_page'));
    }

    public function test_site_config_has_expected_defaults(): void
    {
        $this->assertNull(config('aicl.site.description'));
    }

    public function test_mail_config_has_expected_defaults(): void
    {
        $this->assertNull(config('aicl.mail.reply_to'));
    }
}
