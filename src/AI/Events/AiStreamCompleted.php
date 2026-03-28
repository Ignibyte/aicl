<?php

declare(strict_types=1);

namespace Aicl\AI\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * AiStreamCompleted.
 */
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
        // @codeCoverageIgnoreStart — Event infrastructure
        return [new PrivateChannel("ai.stream.{$this->streamId}")];
        // @codeCoverageIgnoreEnd
    }

    public function broadcastAs(): string
    {
        // @codeCoverageIgnoreStart — Event infrastructure
        return 'ai.completed';
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
            'total_tokens' => $this->totalTokens,
            'usage' => $this->usage,
        ];
        // @codeCoverageIgnoreEnd
    }
}
