<?php

namespace Aicl\AI;

use Aicl\AI\Jobs\AiConversationStreamJob;
use Aicl\Enums\AiMessageRole;
use Aicl\Models\AiConversation;
use Aicl\Models\AiMessage;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use NeuronAI\Chat\Enums\MessageRole;
use NeuronAI\Chat\Messages\Message;

class AiChatService
{
    /**
     * Verify that a user has role-based access to a conversation's agent.
     *
     * @throws \RuntimeException If the user does not have access
     */
    public function authorizeAccess(AiConversation $conversation, ?User $user): void
    {
        $agent = $conversation->agent;

        if (! $agent) {
            throw new \RuntimeException('AI agent not found.');
        }

        if (! $agent->isAccessibleByUser($user)) {
            throw new \RuntimeException('You do not have access to this AI agent.');
        }
    }

    /**
     * Send a user message to a conversation and trigger AI streaming.
     *
     * Creates the user's AiMessage, enforces concurrent stream limits,
     * and dispatches the conversation stream job.
     *
     * @return array{stream_id: string, channel: string, message_id: string}
     *
     * @throws \RuntimeException If the agent is not available or concurrent limit exceeded
     */
    public function sendMessage(AiConversation $conversation, string $content, ?User $user = null): array
    {
        $agent = $conversation->agent;

        if (! $agent || ! $agent->is_active || ! $agent->is_configured) {
            throw new \RuntimeException('AI agent is not available or not configured.');
        }

        // Enforce role-based access
        if ($user && ! $agent->isAccessibleByUser($user)) {
            throw new \RuntimeException('You do not have access to this AI agent.');
        }

        // Create user message
        $userMessage = $conversation->messages()->create([
            'role' => AiMessageRole::User,
            'content' => $content,
        ]);

        // Generate stream ID
        $streamId = (string) Str::uuid();
        $userId = $conversation->user_id;

        // Store user for channel authorization (auto-expires in 5 minutes)
        Cache::put("ai-stream:{$streamId}:user", $userId, 300);

        // Enforce concurrent stream limit (atomic increment to prevent TOCTOU race)
        $maxConcurrent = (int) config('aicl.ai.streaming.max_concurrent_per_user', 2);
        $countKey = "ai-stream:user:{$userId}:count";

        // Initialize key if missing (increment requires existing key on some drivers)
        Cache::add($countKey, 0, 300);
        $newCount = (int) Cache::increment($countKey);

        if ($newCount > $maxConcurrent) {
            Cache::decrement($countKey);

            throw new \RuntimeException('Too many concurrent AI streams. Please wait for a current stream to finish.');
        }

        // Dispatch conversation stream job
        AiConversationStreamJob::dispatch(
            streamId: $streamId,
            conversationId: $conversation->id,
        );

        return [
            'stream_id' => $streamId,
            'channel' => "private-ai.stream.{$streamId}",
            'message_id' => $userMessage->id,
        ];
    }

    /**
     * Build the message history for a conversation.
     *
     * Respects the agent's context_messages limit.
     * Includes conversation summary as system context if available.
     *
     * @return array<int, Message>
     */
    public function buildMessageHistory(AiConversation $conversation): array
    {
        $agent = $conversation->agent;
        $limit = $agent->context_messages;

        $messages = [];

        // If conversation has been compacted, include summary as system context
        if ($conversation->summary) {
            $messages[] = new Message(
                MessageRole::SYSTEM,
                "Previous conversation summary:\n{$conversation->summary}",
            );
        }

        // Load recent messages up to the agent's context_messages limit
        $recentMessages = $conversation->messages()
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values();

        foreach ($recentMessages as $msg) {
            /** @var AiMessage $msg */
            $role = match ($msg->role) {
                AiMessageRole::User => MessageRole::USER,
                AiMessageRole::Assistant => MessageRole::ASSISTANT,
                AiMessageRole::System => MessageRole::SYSTEM,
            };

            $messages[] = new Message($role, $msg->content);
        }

        return $messages;
    }
}
