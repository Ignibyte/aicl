<?php

namespace Aicl\Tests\Unit\Settings;

use Aicl\Settings\FeatureSettings;
use Aicl\Settings\GeneralSettings;
use Aicl\Settings\MailSettings;
use PHPUnit\Framework\TestCase;
use Spatie\LaravelSettings\Settings;

class SettingsTest extends TestCase
{
    // ─── GeneralSettings ────────────────────────────────────

    public function test_general_settings_extends_spatie_settings(): void
    {
        $this->assertTrue(is_subclass_of(GeneralSettings::class, Settings::class));
    }

    public function test_general_settings_group_is_general(): void
    {
        $this->assertEquals('general', GeneralSettings::group());
    }

    public function test_general_settings_has_expected_properties(): void
    {
        $reflection = new \ReflectionClass(GeneralSettings::class);

        $this->assertTrue($reflection->hasProperty('site_name'));
        $this->assertTrue($reflection->hasProperty('site_description'));
        $this->assertTrue($reflection->hasProperty('timezone'));
        $this->assertTrue($reflection->hasProperty('date_format'));
        $this->assertTrue($reflection->hasProperty('items_per_page'));
        $this->assertTrue($reflection->hasProperty('maintenance_mode'));
    }

    // ─── MailSettings ───────────────────────────────────────

    public function test_mail_settings_extends_spatie_settings(): void
    {
        $this->assertTrue(is_subclass_of(MailSettings::class, Settings::class));
    }

    public function test_mail_settings_group_is_mail(): void
    {
        $this->assertEquals('mail', MailSettings::group());
    }

    public function test_mail_settings_has_expected_properties(): void
    {
        $reflection = new \ReflectionClass(MailSettings::class);

        $this->assertTrue($reflection->hasProperty('from_address'));
        $this->assertTrue($reflection->hasProperty('from_name'));
        $this->assertTrue($reflection->hasProperty('reply_to'));
    }

    // ─── FeatureSettings ────────────────────────────────────

    public function test_feature_settings_extends_spatie_settings(): void
    {
        $this->assertTrue(is_subclass_of(FeatureSettings::class, Settings::class));
    }

    public function test_feature_settings_group_is_features(): void
    {
        $this->assertEquals('features', FeatureSettings::group());
    }

    public function test_feature_settings_has_expected_properties(): void
    {
        $reflection = new \ReflectionClass(FeatureSettings::class);

        $this->assertTrue($reflection->hasProperty('enable_registration'));
        $this->assertTrue($reflection->hasProperty('enable_social_login'));
        $this->assertTrue($reflection->hasProperty('enable_mfa'));
        $this->assertTrue($reflection->hasProperty('enable_api'));
    }
}
