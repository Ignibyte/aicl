<?php

declare(strict_types=1);

namespace Aicl\AI\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * AiTokenEvent.
 */
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
        // @codeCoverageIgnoreStart — Event infrastructure
        return [new PrivateChannel("ai.stream.{$this->streamId}")];
        // @codeCoverageIgnoreEnd
    }

    public function broadcastAs(): string
    {
        // @codeCoverageIgnoreStart — Event infrastructure
        return 'ai.token';
        // @codeCoverageIgnoreEnd
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        // @codeCoverageIgnoreStart — Event infrastructure
        return [
            'stream_id' => $this->streamId,
            'token' => $this->token,
            'index' => $this->index,
        ];
        // @codeCoverageIgnoreEnd
    }
}
