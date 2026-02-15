<?php

namespace Aicl\Tests\Unit\Notifications\Templates\Resolvers;

use Aicl\Notifications\Templates\Contracts\VariableResolver;
use Aicl\Notifications\Templates\Resolvers\ChannelVariableResolver;
use PHPUnit\Framework\TestCase;

class ChannelVariableResolverTest extends TestCase
{
    private ChannelVariableResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new ChannelVariableResolver;
    }

    public function test_implements_variable_resolver(): void
    {
        $this->assertInstanceOf(VariableResolver::class, $this->resolver);
    }

    public function test_resolves_channel_field(): void
    {
        $channel = (object) ['name' => 'General Slack', 'slug' => 'general-slack'];

        $result = $this->resolver->resolve('name', ['channel' => $channel]);

        $this->assertSame('General Slack', $result);
    }

    public function test_resolves_slug_field(): void
    {
        $channel = (object) ['name' => 'General Slack', 'slug' => 'general-slack'];

        $result = $this->resolver->resolve('slug', ['channel' => $channel]);

        $this->assertSame('general-slack', $result);
    }

    public function test_denies_config_field(): void
    {
        $channel = (object) ['config' => ['api_key' => 'secret'], 'name' => 'Test'];

        $result = $this->resolver->resolve('config', ['channel' => $channel]);

        $this->assertNull($result);
    }

    public function test_returns_null_when_no_channel_in_context(): void
    {
        $result = $this->resolver->resolve('name', []);

        $this->assertNull($result);
    }

    public function test_returns_null_when_channel_is_not_object(): void
    {
        $result = $this->resolver->resolve('name', ['channel' => 'not an object']);

        $this->assertNull($result);
    }

    public function test_returns_null_for_nonexistent_field(): void
    {
        $channel = (object) ['name' => 'Test'];

        $result = $this->resolver->resolve('nonexistent', ['channel' => $channel]);

        $this->assertNull($result);
    }

    public function test_returns_null_for_null_field_value(): void
    {
        $channel = (object) ['name' => null];

        $result = $this->resolver->resolve('name', ['channel' => $channel]);

        $this->assertNull($result);
    }

    public function test_returns_integer_as_string(): void
    {
        $channel = (object) ['id' => 7];

        $result = $this->resolver->resolve('id', ['channel' => $channel]);

        $this->assertSame('7', $result);
    }
}
