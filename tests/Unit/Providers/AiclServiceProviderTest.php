<?php

namespace Aicl\Tests\Unit\Providers;

use Aicl\AiclServiceProvider;
use Illuminate\Support\ServiceProvider;
use PHPUnit\Framework\TestCase;

class AiclServiceProviderTest extends TestCase
{
    public function test_extends_service_provider(): void
    {
        $this->assertTrue((new \ReflectionClass(AiclServiceProvider::class))->isSubclassOf(ServiceProvider::class));
    }

    public function test_defines_register_method(): void
    {
        $this->assertTrue((new \ReflectionClass(AiclServiceProvider::class))->hasMethod('register'));
    }

    public function test_defines_boot_method(): void
    {
        $this->assertTrue((new \ReflectionClass(AiclServiceProvider::class))->hasMethod('boot'));
    }
}
