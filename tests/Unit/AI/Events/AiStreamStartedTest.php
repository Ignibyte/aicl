<?php

namespace Aicl\Tests\Unit\AI\Events;

use Aicl\AI\Events\AiStreamStarted;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Tests\TestCase;

class AiStreamStartedTest extends TestCase
{
    public function test_implements_should_broadcast_now(): void
    {
        $event = new AiStreamStarted('stream-123', 1);

        $this->assertInstanceOf(ShouldBroadcastNow::class, $event);
    }

    public function test_broadcast_as_returns_ai_started(): void
    {
        $event = new AiStreamStarted('stream-123', 1);

        $this->assertSame('ai.started', $event->broadcastAs());
    }

    public function test_broadcast_on_returns_private_stream_channel(): void
    {
        $event = new AiStreamStarted('abc-uuid', 1);

        $channels = $event->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
        $this->assertSame('private-ai.stream.abc-uuid', $channels[0]->name);
    }

    public function test_broadcast_with_contains_stream_id(): void
    {
        $event = new AiStreamStarted('stream-456', 1);

        $data = $event->broadcastWith();

        $this->assertSame(['stream_id' => 'stream-456'], $data);
    }

    public function test_constructor_sets_properties(): void
    {
        $event = new AiStreamStarted('my-stream', 42);

        $this->assertSame('my-stream', $event->streamId);
        $this->assertSame(42, $event->userId);
    }
}
