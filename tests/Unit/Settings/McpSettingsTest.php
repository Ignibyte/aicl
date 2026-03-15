<?php

namespace Aicl\Tests\Unit\Settings;

use Aicl\Settings\McpSettings;
use PHPUnit\Framework\TestCase;
use Spatie\LaravelSettings\Settings;

class McpSettingsTest extends TestCase
{
    public function test_mcp_settings_extends_settings(): void
    {
        $this->assertTrue(is_subclass_of(McpSettings::class, Settings::class));
    }

    public function test_settings_group_is_mcp(): void
    {
        $this->assertSame('mcp', McpSettings::group());
    }

    public function test_settings_has_required_properties(): void
    {
        $reflection = new \ReflectionClass(McpSettings::class);

        $this->assertTrue($reflection->hasProperty('is_enabled'));
        $this->assertTrue($reflection->hasProperty('exposed_entities'));
        $this->assertTrue($reflection->hasProperty('custom_tools_enabled'));
        $this->assertTrue($reflection->hasProperty('rate_limit_per_minute'));
        $this->assertTrue($reflection->hasProperty('max_sessions'));
        $this->assertTrue($reflection->hasProperty('server_description'));
    }

    public function test_is_enabled_property_is_bool(): void
    {
        $reflection = new \ReflectionClass(McpSettings::class);
        $property = $reflection->getProperty('is_enabled');
        $type = $property->getType();

        $this->assertNotNull($type);
        $this->assertSame('bool', $type->getName());
    }

    public function test_exposed_entities_property_is_array(): void
    {
        $reflection = new \ReflectionClass(McpSettings::class);
        $property = $reflection->getProperty('exposed_entities');
        $type = $property->getType();

        $this->assertNotNull($type);
        $this->assertSame('array', $type->getName());
    }

    public function test_custom_tools_enabled_property_is_bool(): void
    {
        $reflection = new \ReflectionClass(McpSettings::class);
        $property = $reflection->getProperty('custom_tools_enabled');
        $type = $property->getType();

        $this->assertNotNull($type);
        $this->assertSame('bool', $type->getName());
    }

    public function test_rate_limit_per_minute_property_is_int(): void
    {
        $reflection = new \ReflectionClass(McpSettings::class);
        $property = $reflection->getProperty('rate_limit_per_minute');
        $type = $property->getType();

        $this->assertNotNull($type);
        $this->assertSame('int', $type->getName());
    }

    public function test_max_sessions_property_is_int(): void
    {
        $reflection = new \ReflectionClass(McpSettings::class);
        $property = $reflection->getProperty('max_sessions');
        $type = $property->getType();

        $this->assertNotNull($type);
        $this->assertSame('int', $type->getName());
    }

    public function test_server_description_property_is_nullable_string(): void
    {
        $reflection = new \ReflectionClass(McpSettings::class);
        $property = $reflection->getProperty('server_description');
        $type = $property->getType();

        $this->assertNotNull($type);
        $this->assertSame('string', $type->getName());
        $this->assertTrue($type->allowsNull());
    }
}
