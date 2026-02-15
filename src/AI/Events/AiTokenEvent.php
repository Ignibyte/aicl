<?php

namespace Aicl\AI\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class AiTokenEvent implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;

    public function __construct(
        public string $streamId,
        public int $userId,
        public string $token,
        public int $index,
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
        return 'ai.token';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'stream_id' => $this->streamId,
            'token' => $this->token,
            'index' => $this->index,
        ];
    }
}
