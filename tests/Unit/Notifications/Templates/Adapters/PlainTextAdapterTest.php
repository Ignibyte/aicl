<?php

namespace Aicl\Tests\Unit\Notifications\Templates\Adapters;

use Aicl\Notifications\Enums\ChannelType;
use Aicl\Notifications\Templates\Adapters\PlainTextAdapter;
use Aicl\Notifications\Templates\Contracts\ChannelFormatAdapter;
use PHPUnit\Framework\TestCase;

class PlainTextAdapterTest extends TestCase
{
    private PlainTextAdapter $adapter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adapter = new PlainTextAdapter;
    }

    public function test_implements_channel_format_adapter(): void
    {
        $this->assertInstanceOf(ChannelFormatAdapter::class, $this->adapter);
    }

    public function test_channel_type_is_sms(): void
    {
        $this->assertSame(ChannelType::Sms, $this->adapter->channelType());
    }

    public function test_strips_html_from_title_and_body(): void
    {
        /** @phpstan-ignore-next-line */
        $result = $this->adapter->format([
            'title' => '<b>Important</b> Alert',
            'body' => '<p>Something happened</p>',
        ], []);

        $this->assertSame('Important Alert', $result['title']);
        $this->assertSame('Something happened', $result['body']);
    }

    public function test_returns_title_and_body_keys(): void
    {
        /** @phpstan-ignore-next-line */
        $result = $this->adapter->format([
            'title' => 'Title',
            'body' => 'Body',
        ], []);

        $this->assertArrayHasKey('title', $result);
        $this->assertArrayHasKey('body', $result);
        $this->assertCount(2, $result);
    }

    public function test_handles_missing_keys(): void
    {
        /** @phpstan-ignore-next-line */
        $result = $this->adapter->format([], []);

        $this->assertSame('', $result['title']);
        $this->assertSame('', $result['body']);
    }

    public function test_plain_text_passes_through(): void
    {
        /** @phpstan-ignore-next-line */
        $result = $this->adapter->format([
            'title' => 'Plain Title',
            'body' => 'Plain body text',
        ], []);

        $this->assertSame('Plain Title', $result['title']);
        $this->assertSame('Plain body text', $result['body']);
    }
}
