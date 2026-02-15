<?php

namespace Aicl\AI\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class AiToolCallEvent implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;

    /**
     * @param  array<int, array{name: string, inputs: array<string, mixed>}>  $tools
     */
    public function __construct(
        public string $streamId,
        public int $userId,
        public array $tools,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("ai.stream.{$this->streamId}")];
    }

    public function broadcastAs(): string
    {
        return 'ai.tool_call';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'stream_id' => $this->streamId,
            'tools' => $this->tools,
        ];
    }
}
