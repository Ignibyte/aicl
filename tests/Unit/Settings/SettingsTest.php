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

    public function test_general_settings_extends_settings(): void
    {
        $this->assertTrue(is_subclass_of(GeneralSettings::class, Settings::class));
    }

    public function test_general_settings_group(): void
    {
        $this->assertSame('general', GeneralSettings::group());
    }

    public function test_general_settings_has_required_properties(): void
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

    public function test_mail_settings_extends_settings(): void
    {
        $this->assertTrue(is_subclass_of(MailSettings::class, Settings::class));
    }

    public function test_mail_settings_group(): void
    {
        $this->assertSame('mail', MailSettings::group());
    }

    public function test_mail_settings_has_required_properties(): void
    {
        $reflection = new \ReflectionClass(MailSettings::class);

        $this->assertTrue($reflection->hasProperty('from_address'));
        $this->assertTrue($reflection->hasProperty('from_name'));
        $this->assertTrue($reflection->hasProperty('reply_to'));
    }

    // ─── FeatureSettings ────────────────────────────────────

    public function test_feature_settings_extends_settings(): void
    {
        $this->assertTrue(is_subclass_of(FeatureSettings::class, Settings::class));
    }

    public function test_feature_settings_group(): void
    {
        $this->assertSame('features', FeatureSettings::group());
    }

    public function test_feature_settings_has_required_properties(): void
    {
        $reflection = new \ReflectionClass(FeatureSettings::class);

        $this->assertTrue($reflection->hasProperty('enable_registration'));
        $this->assertTrue($reflection->hasProperty('enable_social_login'));
        $this->assertTrue($reflection->hasProperty('enable_mfa'));
        $this->assertTrue($reflection->hasProperty('enable_saml'));
        $this->assertTrue($reflection->hasProperty('enable_api'));
    }

    public function test_feature_settings_properties_are_boolean(): void
    {
        $reflection = new \ReflectionClass(FeatureSettings::class);

        $booleanProperties = [
            'enable_registration',
            'enable_social_login',
            'enable_mfa',
            'enable_saml',
            'enable_api',
        ];

        foreach ($booleanProperties as $propertyName) {
            $property = $reflection->getProperty($propertyName);
            $type = $property->getType();

            $this->assertNotNull($type, "Property {$propertyName} should have a type declaration");
            $this->assertSame('bool', $type->getName(), "Property {$propertyName} should be typed as bool");
        }
    }
}
