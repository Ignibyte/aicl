<?php

namespace Aicl\Tests\Unit\Notifications\Templates;

use Aicl\Notifications\Enums\ChannelType;
use Aicl\Notifications\Templates\Adapters\PlainTextAdapter;
use Aicl\Notifications\Templates\Adapters\SlackBlockAdapter;
use Aicl\Notifications\Templates\FormatAdapterRegistry;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class FormatAdapterRegistryTest extends TestCase
{
    private FormatAdapterRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = new FormatAdapterRegistry;
    }

    public function test_register_and_resolve_adapter(): void
    {
        $adapter = new PlainTextAdapter;
        $this->registry->register(ChannelType::Sms, $adapter);

        $this->assertSame($adapter, $this->registry->resolve(ChannelType::Sms));
    }

    public function test_has_returns_true_for_registered_adapter(): void
    {
        $this->registry->register(ChannelType::Slack, new SlackBlockAdapter);

        $this->assertTrue($this->registry->has(ChannelType::Slack));
    }

    public function test_has_returns_false_for_unregistered_adapter(): void
    {
        $this->assertFalse($this->registry->has(ChannelType::PagerDuty));
    }

    public function test_all_returns_all_registered_adapters(): void
    {
        $sms = new PlainTextAdapter;
        $slack = new SlackBlockAdapter;

        $this->registry->register(ChannelType::Sms, $sms);
        $this->registry->register(ChannelType::Slack, $slack);

        $all = $this->registry->all();

        $this->assertCount(2, $all);
        $this->assertSame($sms, $all[ChannelType::Sms->value]);
        $this->assertSame($slack, $all[ChannelType::Slack->value]);
    }

    public function test_all_returns_empty_when_no_adapters(): void
    {
        $this->assertSame([], $this->registry->all());
    }

    public function test_resolve_throws_on_unknown_channel_type(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Format adapter for channel type [webhook] is not registered.');

        $this->registry->resolve(ChannelType::Webhook);
    }

    public function test_register_overwrites_existing_adapter(): void
    {
        $first = new PlainTextAdapter;
        $second = new PlainTextAdapter;

        $this->registry->register(ChannelType::Sms, $first);
        $this->registry->register(ChannelType::Sms, $second);

        $this->assertSame($second, $this->registry->resolve(ChannelType::Sms));
    }
}
