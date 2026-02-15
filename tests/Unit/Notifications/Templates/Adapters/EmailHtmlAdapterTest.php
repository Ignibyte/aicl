<?php

namespace Aicl\Tests\Unit\Notifications\Templates\Adapters;

use Aicl\Notifications\Enums\ChannelType;
use Aicl\Notifications\Templates\Adapters\EmailHtmlAdapter;
use Aicl\Notifications\Templates\Contracts\ChannelFormatAdapter;
use PHPUnit\Framework\TestCase;

class EmailHtmlAdapterTest extends TestCase
{
    private EmailHtmlAdapter $adapter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adapter = new EmailHtmlAdapter;
    }

    public function test_implements_channel_format_adapter(): void
    {
        $this->assertInstanceOf(ChannelFormatAdapter::class, $this->adapter);
    }

    public function test_channel_type_is_email(): void
    {
        $this->assertSame(ChannelType::Email, $this->adapter->channelType());
    }

    public function test_wraps_in_html_with_title(): void
    {
        $result = $this->adapter->format([
            'title' => 'Welcome Email',
            'body' => 'Hello there!',
        ], []);

        $this->assertSame('Welcome Email', $result['title']);
        $this->assertSame('Welcome Email', $result['subject']);
        $this->assertStringContainsString('<h2', $result['body']);
        $this->assertStringContainsString('Welcome Email', $result['body']);
    }

    public function test_body_contains_paragraph_with_text(): void
    {
        $result = $this->adapter->format([
            'title' => 'Title',
            'body' => 'Body content here',
        ], []);

        $this->assertStringContainsString('<p', $result['body']);
        $this->assertStringContainsString('Body content here', $result['body']);
    }

    public function test_includes_action_button_when_url_present(): void
    {
        $result = $this->adapter->format([
            'title' => 'Title',
            'body' => 'Body',
            'action_url' => 'https://example.com/action',
            'action_text' => 'Click Here',
        ], []);

        $this->assertStringContainsString('href="https://example.com/action"', $result['body']);
        $this->assertStringContainsString('Click Here', $result['body']);
        $this->assertSame('https://example.com/action', $result['action_url']);
        $this->assertSame('Click Here', $result['action_text']);
    }

    public function test_no_action_button_when_url_is_null(): void
    {
        $result = $this->adapter->format([
            'title' => 'Title',
            'body' => 'Body',
            'action_url' => null,
        ], []);

        $this->assertStringNotContainsString('href=', $result['body']);
    }

    public function test_uses_default_action_text(): void
    {
        $result = $this->adapter->format([
            'title' => 'Title',
            'body' => 'Body',
            'action_url' => 'https://example.com',
        ], []);

        $this->assertStringContainsString('View Details', $result['body']);
    }

    public function test_output_contains_required_keys(): void
    {
        $result = $this->adapter->format([
            'title' => 'Title',
            'body' => 'Body',
        ], []);

        $this->assertArrayHasKey('title', $result);
        $this->assertArrayHasKey('subject', $result);
        $this->assertArrayHasKey('body', $result);
        $this->assertArrayHasKey('action_url', $result);
        $this->assertArrayHasKey('action_text', $result);
    }

    public function test_html_has_inline_styles(): void
    {
        $result = $this->adapter->format([
            'title' => 'Styled Email',
            'body' => 'Content',
        ], []);

        $this->assertStringContainsString('style=', $result['body']);
        $this->assertStringContainsString('font-family', $result['body']);
    }
}
