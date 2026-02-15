<?php

namespace Aicl\Tests\Unit\AI\Events;

use Aicl\AI\Events\AiTokenEvent;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Tests\TestCase;

class AiTokenEventTest extends TestCase
{
    public function test_implements_should_broadcast_now(): void
    {
        $event = new AiTokenEvent('stream-123', 1, 'Hello', 0);

        $this->assertInstanceOf(ShouldBroadcastNow::class, $event);
    }

    public function test_broadcast_as_returns_ai_token(): void
    {
        $event = new AiTokenEvent('stream-123', 1, 'Hello', 0);

        $this->assertSame('ai.token', $event->broadcastAs());
    }

    public function test_broadcast_on_returns_private_stream_channel(): void
    {
        $event = new AiTokenEvent('abc-uuid', 1, 'token', 0);

        $channels = $event->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
        $this->assertSame('private-ai.stream.abc-uuid', $channels[0]->name);
    }

    public function test_broadcast_with_contains_token_payload(): void
    {
        $event = new AiTokenEvent('stream-456', 1, 'world', 5);

        $data = $event->broadcastWith();

        $this->assertSame([
            'stream_id' => 'stream-456',
            'token' => 'world',
            'index' => 5,
        ], $data);
    }

    public function test_constructor_sets_properties(): void
    {
        $event = new AiTokenEvent('my-stream', 42, 'Hello', 3);

        $this->assertSame('my-stream', $event->streamId);
        $this->assertSame(42, $event->userId);
        $this->assertSame('Hello', $event->token);
        $this->assertSame(3, $event->index);
    }
}
