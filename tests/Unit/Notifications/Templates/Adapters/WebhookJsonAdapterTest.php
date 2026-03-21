<?php

namespace Aicl\Tests\Unit\Notifications\Templates\Adapters;

use Aicl\Notifications\Enums\ChannelType;
use Aicl\Notifications\Templates\Adapters\WebhookJsonAdapter;
use Aicl\Notifications\Templates\Contracts\ChannelFormatAdapter;
use PHPUnit\Framework\TestCase;

class WebhookJsonAdapterTest extends TestCase
{
    private WebhookJsonAdapter $adapter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adapter = new WebhookJsonAdapter;
    }

    public function test_implements_channel_format_adapter(): void
    {
        $this->assertInstanceOf(ChannelFormatAdapter::class, $this->adapter);
    }

    public function test_channel_type_is_webhook(): void
    {
        $this->assertSame(ChannelType::Webhook, $this->adapter->channelType());
    }

    public function test_passes_rendered_payload_through(): void
    {
        $rendered = [
            'title' => 'Webhook Title',
            'body' => 'Webhook Body',
            'action_url' => 'https://example.com',
            'action_text' => 'View',
            'color' => 'primary',
        ];

        $result = $this->adapter->format($rendered, []);

        $this->assertSame($rendered, $result);
    }

    public function test_passes_empty_array_through(): void
    {
        /** @phpstan-ignore-next-line */
        $result = $this->adapter->format([], []);

        $this->assertSame([], $result);
    }

    public function test_ignores_context(): void
    {
        $rendered = ['title' => 'Test'];
        $context = ['extra' => 'data'];

        /** @phpstan-ignore-next-line */
        $result = $this->adapter->format($rendered, $context);

        $this->assertSame($rendered, $result);
    }

    public function test_preserves_all_keys(): void
    {
        $rendered = [
            'title' => 'Title',
            'body' => 'Body',
            'custom_key' => 'custom_value',
        ];

        /** @phpstan-ignore-next-line */
        $result = $this->adapter->format($rendered, []);

        $this->assertArrayHasKey('custom_key', $result);
        $this->assertSame('custom_value', $result['custom_key']);
    }
}
