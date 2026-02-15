<?php

namespace Aicl\Tests\Unit\Notifications\Templates\Resolvers;

use Aicl\Notifications\Templates\Contracts\VariableResolver;
use Aicl\Notifications\Templates\Resolvers\AppVariableResolver;
use Tests\TestCase;

class AppVariableResolverTest extends TestCase
{
    private AppVariableResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new AppVariableResolver;
    }

    public function test_implements_variable_resolver(): void
    {
        $this->assertInstanceOf(VariableResolver::class, $this->resolver);
    }

    public function test_resolves_allowed_name_field(): void
    {
        config(['app.name' => 'Test App']);

        $result = $this->resolver->resolve('name', []);

        $this->assertSame('Test App', $result);
    }

    public function test_resolves_allowed_url_field(): void
    {
        config(['app.url' => 'https://example.com']);

        $result = $this->resolver->resolve('url', []);

        $this->assertSame('https://example.com', $result);
    }

    public function test_resolves_allowed_env_field(): void
    {
        config(['app.env' => 'testing']);

        $result = $this->resolver->resolve('env', []);

        $this->assertSame('testing', $result);
    }

    public function test_resolves_allowed_timezone_field(): void
    {
        config(['app.timezone' => 'UTC']);

        $result = $this->resolver->resolve('timezone', []);

        $this->assertSame('UTC', $result);
    }

    public function test_blocks_key_field(): void
    {
        $result = $this->resolver->resolve('key', []);

        $this->assertNull($result);
    }

    public function test_blocks_debug_field(): void
    {
        $result = $this->resolver->resolve('debug', []);

        $this->assertNull($result);
    }

    public function test_blocks_arbitrary_field(): void
    {
        $result = $this->resolver->resolve('cipher', []);

        $this->assertNull($result);
    }

    public function test_returns_null_when_config_value_is_null(): void
    {
        config(['app.name' => null]);

        $result = $this->resolver->resolve('name', []);

        $this->assertNull($result);
    }
}
