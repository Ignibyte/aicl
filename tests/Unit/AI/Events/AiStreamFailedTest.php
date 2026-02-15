<?php

namespace Aicl\Tests\Unit\AI\Events;

use Aicl\AI\Events\AiStreamFailed;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Tests\TestCase;

class AiStreamFailedTest extends TestCase
{
    public function test_implements_should_broadcast_now(): void
    {
        $event = new AiStreamFailed('stream-123', 1, 'Something went wrong');

        $this->assertInstanceOf(ShouldBroadcastNow::class, $event);
    }

    public function test_broadcast_as_returns_ai_failed(): void
    {
        $event = new AiStreamFailed('stream-123', 1, 'Error occurred');

        $this->assertSame('ai.failed', $event->broadcastAs());
    }

    public function test_broadcast_on_returns_private_stream_channel(): void
    {
        $event = new AiStreamFailed('abc-uuid', 1, 'Error');

        $channels = $event->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
        $this->assertSame('private-ai.stream.abc-uuid', $channels[0]->name);
    }

    public function test_broadcast_with_contains_error_payload(): void
    {
        $event = new AiStreamFailed('stream-456', 1, 'An error occurred while generating the response.');

        $data = $event->broadcastWith();

        $this->assertSame([
            'stream_id' => 'stream-456',
            'error' => 'An error occurred while generating the response.',
        ], $data);
    }

    public function test_constructor_sets_properties(): void
    {
        $event = new AiStreamFailed('my-stream', 42, 'Timeout');

        $this->assertSame('my-stream', $event->streamId);
        $this->assertSame(42, $event->userId);
        $this->assertSame('Timeout', $event->error);
    }
}
