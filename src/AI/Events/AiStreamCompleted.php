<?php

namespace Aicl\AI\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class AiStreamCompleted implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;

    /**
     * @param  array<string, mixed>  $usage
     */
    public function __construct(
        public string $streamId,
        public int $userId,
        public int $totalTokens,
        public array $usage = [],
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
        return 'ai.completed';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'stream_id' => $this->streamId,
            'total_tokens' => $this->totalTokens,
            'usage' => $this->usage,
        ];
    }
}
