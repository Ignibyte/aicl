<?php

declare(strict_types=1);

namespace Aicl\AI\Jobs;

use Aicl\AI\CompactionService;
use Aicl\Models\AiConversation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * CompactConversationJob.
 */
class CompactConversationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(
        public string $conversationId,
    ) {
        $this->onQueue(config('aicl.ai.streaming.queue', 'default'));
    }

    public function handle(CompactionService $service): void
    {
        $conversation = AiConversation::with('agent')->find($this->conversationId);

        if (! $conversation) {
            Log::warning('CompactConversationJob: conversation not found', [
                'conversation_id' => $this->conversationId,
            ]);

            return;
        }

        if (! $conversation->is_compactable) {
            return;
        }

        try {
            $service->compact($conversation);
        } catch (Throwable $e) {
            Log::error('Conversation compaction failed', [
                'conversation_id' => $this->conversationId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
