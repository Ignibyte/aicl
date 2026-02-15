<?php

namespace Aicl\Tests\Unit\AI\Events;

use Aicl\AI\Events\AiStreamCompleted;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Tests\TestCase;

class AiStreamCompletedTest extends TestCase
{
    public function test_implements_should_broadcast_now(): void
    {
        $event = new AiStreamCompleted('stream-123', 1, 50);

        $this->assertInstanceOf(ShouldBroadcastNow::class, $event);
    }

    public function test_broadcast_as_returns_ai_completed(): void
    {
        $event = new AiStreamCompleted('stream-123', 1, 50);

        $this->assertSame('ai.completed', $event->broadcastAs());
    }

    public function test_broadcast_on_returns_private_stream_channel(): void
    {
        $event = new AiStreamCompleted('abc-uuid', 1, 50);

        $channels = $event->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
        $this->assertSame('private-ai.stream.abc-uuid', $channels[0]->name);
    }

    public function test_broadcast_with_contains_completion_payload(): void
    {
        $usage = ['prompt_tokens' => 10, 'completion_tokens' => 50];
        $event = new AiStreamCompleted('stream-456', 1, 50, $usage);

        $data = $event->broadcastWith();

        $this->assertSame([
            'stream_id' => 'stream-456',
            'total_tokens' => 50,
            'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 50],
        ], $data);
    }

    public function test_broadcast_with_defaults_usage_to_empty_array(): void
    {
        $event = new AiStreamCompleted('stream-789', 1, 25);

        $data = $event->broadcastWith();

        $this->assertSame([], $data['usage']);
    }

    public function test_constructor_sets_properties(): void
    {
        $usage = ['total' => 100];
        $event = new AiStreamCompleted('my-stream', 42, 75, $usage);

        $this->assertSame('my-stream', $event->streamId);
        $this->assertSame(42, $event->userId);
        $this->assertSame(75, $event->totalTokens);
        $this->assertSame(['total' => 100], $event->usage);
    }
}
