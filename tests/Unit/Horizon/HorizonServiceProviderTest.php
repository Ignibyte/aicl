<?php

namespace Aicl\Tests\Unit\Horizon;

use Aicl\Horizon\EventMap;
use Aicl\Horizon\HorizonServiceProvider;
use Aicl\Horizon\ServiceBindings;
use Illuminate\Support\ServiceProvider;
use PHPUnit\Framework\TestCase;

class HorizonServiceProviderTest extends TestCase
{
    public function test_extends_service_provider(): void
    {
        $this->assertTrue((new \ReflectionClass(HorizonServiceProvider::class))->isSubclassOf(ServiceProvider::class));
    }

    public function test_uses_event_map_trait(): void
    {
        $traits = class_uses(HorizonServiceProvider::class);

        $this->assertArrayHasKey(EventMap::class, $traits);
    }

    public function test_uses_service_bindings_trait(): void
    {
        $traits = class_uses(HorizonServiceProvider::class);

        $this->assertArrayHasKey(ServiceBindings::class, $traits);
    }

    public function test_has_register_method(): void
    {
        $this->assertTrue((new \ReflectionClass(HorizonServiceProvider::class))->hasMethod('register'));
    }

    public function test_has_boot_method(): void
    {
        $this->assertTrue((new \ReflectionClass(HorizonServiceProvider::class))->hasMethod('boot'));
    }
}
