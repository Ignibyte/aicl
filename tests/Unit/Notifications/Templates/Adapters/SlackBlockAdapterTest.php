<?php

namespace Aicl\Tests\Unit\Notifications\Templates\Adapters;

use Aicl\Notifications\Enums\ChannelType;
use Aicl\Notifications\Templates\Adapters\SlackBlockAdapter;
use Aicl\Notifications\Templates\Contracts\ChannelFormatAdapter;
use PHPUnit\Framework\TestCase;

class SlackBlockAdapterTest extends TestCase
{
    private SlackBlockAdapter $adapter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adapter = new SlackBlockAdapter;
    }

    public function test_implements_channel_format_adapter(): void
    {
        $this->assertInstanceOf(ChannelFormatAdapter::class, $this->adapter);
    }

    public function test_channel_type_is_slack(): void
    {
        $this->assertSame(ChannelType::Slack, $this->adapter->channelType());
    }

    public function test_produces_text_field(): void
    {
        /** @phpstan-ignore-next-line */
        $result = $this->adapter->format([
            'title' => 'Alert Title',
            'body' => 'Alert body text',
        ], []);

        $this->assertSame('Alert Title', $result['text']);
    }

    public function test_produces_attachments_with_body_and_color(): void
    {
        /** @phpstan-ignore-next-line */
        $result = $this->adapter->format([
            'title' => 'Alert',
            'body' => 'Something happened',
            'color' => '#ff0000',
        ], []);

        $this->assertArrayHasKey('attachments', $result);
        $this->assertCount(1, $result['attachments']);
        $this->assertSame('Something happened', $result['attachments'][0]['text']);
        $this->assertSame('#ff0000', $result['attachments'][0]['color']);
    }

    public function test_uses_default_color_when_not_provided(): void
    {
        /** @phpstan-ignore-next-line */
        $result = $this->adapter->format([
            'title' => 'Alert',
            'body' => 'Body',
        ], []);

        $this->assertSame('#3b82f6', $result['attachments'][0]['color']);
    }

    public function test_includes_action_button_when_action_url_present(): void
    {
        /** @phpstan-ignore-next-line */
        $result = $this->adapter->format([
            'title' => 'Alert',
            'body' => 'Body',
            'action_url' => 'https://example.com/view',
            'action_text' => 'View Now',
        ], []);

        $actions = $result['attachments'][0]['actions'];
        $this->assertCount(1, $actions);
        $this->assertSame('button', $actions[0]['type']);
        $this->assertSame('View Now', $actions[0]['text']);
        $this->assertSame('https://example.com/view', $actions[0]['url']);
    }

    public function test_uses_default_action_text(): void
    {
        /** @phpstan-ignore-next-line */
        $result = $this->adapter->format([
            'title' => 'Alert',
            'body' => 'Body',
            'action_url' => 'https://example.com',
        ], []);

        $this->assertSame('View Details', $result['attachments'][0]['actions'][0]['text']);
    }

    public function test_no_actions_when_no_action_url(): void
    {
        /** @phpstan-ignore-next-line */
        $result = $this->adapter->format([
            'title' => 'Alert',
            'body' => 'Body',
        ], []);

        $this->assertArrayNotHasKey('actions', $result['attachments'][0]);
    }

    public function test_no_actions_when_action_url_is_null(): void
    {
        /** @phpstan-ignore-next-line */
        $result = $this->adapter->format([
            'title' => 'Alert',
            'body' => 'Body',
            'action_url' => null,
        ], []);

        $this->assertArrayNotHasKey('actions', $result['attachments'][0]);
    }
}
