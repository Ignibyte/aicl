<?php

namespace Aicl\Tests\Unit\Notifications\Templates\Adapters;

use Aicl\Notifications\Enums\ChannelType;
use Aicl\Notifications\Templates\Adapters\TeamsCardAdapter;
use Aicl\Notifications\Templates\Contracts\ChannelFormatAdapter;
use PHPUnit\Framework\TestCase;

class TeamsCardAdapterTest extends TestCase
{
    private TeamsCardAdapter $adapter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adapter = new TeamsCardAdapter;
    }

    public function test_implements_channel_format_adapter(): void
    {
        $this->assertInstanceOf(ChannelFormatAdapter::class, $this->adapter);
    }

    public function test_channel_type_is_teams(): void
    {
        $this->assertSame(ChannelType::Teams, $this->adapter->channelType());
    }

    public function test_produces_correct_adaptive_card_structure(): void
    {
        $result = $this->adapter->format([
            'title' => 'Teams Alert',
            'body' => 'Something happened',
        ], []);

        $this->assertSame('message', $result['type']);
        $this->assertArrayHasKey('attachments', $result);
        $this->assertCount(1, $result['attachments']);

        $attachment = $result['attachments'][0];
        $this->assertSame('application/vnd.microsoft.card.adaptive', $attachment['contentType']);

        $content = $attachment['content'];
        $this->assertSame('http://adaptivecards.io/schemas/adaptive-card.json', $content['$schema']);
        $this->assertSame('AdaptiveCard', $content['type']);
        $this->assertSame('1.4', $content['version']);
    }

    public function test_body_contains_title_text_block(): void
    {
        $result = $this->adapter->format([
            'title' => 'Teams Alert',
            'body' => 'Body text',
        ], []);

        $body = $result['attachments'][0]['content']['body'];

        $this->assertSame('TextBlock', $body[0]['type']);
        $this->assertSame('Teams Alert', $body[0]['text']);
        $this->assertSame('Bolder', $body[0]['weight']);
        $this->assertSame('Medium', $body[0]['size']);
    }

    public function test_body_contains_body_text_block(): void
    {
        $result = $this->adapter->format([
            'title' => 'Title',
            'body' => 'Body content',
        ], []);

        $body = $result['attachments'][0]['content']['body'];

        $this->assertSame('TextBlock', $body[1]['type']);
        $this->assertSame('Body content', $body[1]['text']);
        $this->assertTrue($body[1]['wrap']);
    }

    public function test_includes_action_when_action_url_present(): void
    {
        $result = $this->adapter->format([
            'title' => 'Title',
            'body' => 'Body',
            'action_url' => 'https://example.com/view',
            'action_text' => 'View Now',
        ], []);

        $actions = $result['attachments'][0]['content']['actions'];

        $this->assertCount(1, $actions);
        $this->assertSame('Action.OpenUrl', $actions[0]['type']);
        $this->assertSame('View Now', $actions[0]['title']);
        $this->assertSame('https://example.com/view', $actions[0]['url']);
    }

    public function test_uses_default_action_text(): void
    {
        $result = $this->adapter->format([
            'title' => 'Title',
            'body' => 'Body',
            'action_url' => 'https://example.com',
        ], []);

        $actions = $result['attachments'][0]['content']['actions'];

        $this->assertSame('View Details', $actions[0]['title']);
    }

    public function test_empty_actions_when_no_action_url(): void
    {
        $result = $this->adapter->format([
            'title' => 'Title',
            'body' => 'Body',
        ], []);

        $actions = $result['attachments'][0]['content']['actions'];

        $this->assertSame([], $actions);
    }

    public function test_empty_actions_when_action_url_is_null(): void
    {
        $result = $this->adapter->format([
            'title' => 'Title',
            'body' => 'Body',
            'action_url' => null,
        ], []);

        $actions = $result['attachments'][0]['content']['actions'];

        $this->assertSame([], $actions);
    }
}
