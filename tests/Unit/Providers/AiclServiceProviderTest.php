<?php

namespace Aicl\Tests\Unit\Providers;

use Aicl\AiclServiceProvider;
use Illuminate\Support\ServiceProvider;
use PHPUnit\Framework\TestCase;

class AiclServiceProviderTest extends TestCase
{
    public function test_extends_service_provider(): void
    {
        $this->assertTrue(is_subclass_of(AiclServiceProvider::class, ServiceProvider::class));
    }

    public function test_defines_register_method(): void
    {
        $this->assertTrue(method_exists(AiclServiceProvider::class, 'register'));
    }

    public function test_defines_boot_method(): void
    {
        $this->assertTrue(method_exists(AiclServiceProvider::class, 'boot'));
    }
}
