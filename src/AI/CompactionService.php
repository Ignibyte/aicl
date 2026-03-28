<?php

declare(strict_types=1);

namespace Aicl\AI;

use Aicl\Enums\AiMessageRole;
use Aicl\Models\AiConversation;
use Aicl\States\AiConversation\Summarized;
use Illuminate\Support\Facades\Log;
use NeuronAI\Agent;
use NeuronAI\Chat\Enums\MessageRole;
use NeuronAI\Chat\Messages\Message;
use NeuronAI\Providers\AIProviderInterface;

/**
 * Manages AI conversation compaction by summarizing old messages to reduce token usage.
 */
class CompactionService
{
    /**
     * Compact a conversation by summarizing older messages.
     *
     * Loads messages outside the context_messages window, sends them to
     * the conversation's AI provider for summarization, stores the result
     * as the conversation's summary, and transitions state to Summarized.
     *
     * @throws \RuntimeException If the conversation cannot be compacted
     */
    public function compact(AiConversation $conversation): void
    {
        if (! $conversation->is_compactable) {
            throw new \RuntimeException('Conversation is not eligible for compaction.');
        }

        $agent = $conversation->agent;

        if (! $agent || ! $agent->is_configured) {
            throw new \RuntimeException('AI agent is not available or not configured for compaction.');
        }

        $provider = AiProviderFactory::makeFromAgent($agent);

        if (! $provider) {
            throw new \RuntimeException('AI provider not configured for compaction.'); // @codeCoverageIgnore
        }

        // Load messages that will be summarized (everything except the most recent N)
        $contextMessages = $agent->context_messages;
        $totalMessages = $conversation->messages()->count();
        $messagesToSummarize = $totalMessages - $contextMessages;

        if ($messagesToSummarize <= 0) {
            return;
        }

        $oldMessages = $conversation->messages()
            ->orderBy('created_at')
            ->limit($messagesToSummarize)
            ->get();

        // Build the conversation text from old messages
        $conversationText = $oldMessages->map(function ($msg): string {
            $role = match ($msg->role) {
                AiMessageRole::User => 'User',
                AiMessageRole::Assistant => 'Assistant',
                AiMessageRole::System => 'System',
            };

            return "[{$role}]: {$msg->content}";
        })->implode("\n\n");

        $summary = $this->summarizeWithAi($provider, $conversationText);

        if (empty($summary)) {
            Log::warning('Compaction produced empty summary', [
                'conversation_id' => $conversation->id,
            ]);

            return;
        }

        // Store summary and transition state
        $conversation->update(['summary' => $summary]);
        $conversation->state->transitionTo(Summarized::class);

        // Optionally delete old messages — uses bulk delete to avoid N observer calls
        if (config('aicl.ai.assistant.compaction_delete_old_messages', false)) {
            $oldMessageIds = $oldMessages->pluck('id');
            $conversation->messages()
                ->whereIn('id', $oldMessageIds)
                ->delete();
        }

        Log::info('Conversation compacted', [
            'conversation_id' => $conversation->id,
            'messages_summarized' => $messagesToSummarize,
            'summary_length' => strlen($summary),
        ]);
    }

    /**
     * Send conversation text to the AI provider for summarization.
     *
     * @codeCoverageIgnore Requires real AI provider to generate summary — compact() flow tested with mocked provider
     */
    protected function summarizeWithAi(AIProviderInterface $provider, string $conversationText): string
    {
        $summaryPrompt = $this->buildSummaryPrompt($conversationText);

        $neuronAgent = Agent::make()
            ->setAiProvider($provider)
            ->setInstructions('You are a conversation summarizer. Produce concise, factual summaries.');

        $response = $neuronAgent->chat([
            new Message(MessageRole::USER, $summaryPrompt),
        ]);

        return trim((string) $response->getContent());
    }

    /**
     * Build the summarization prompt.
     */
    protected function buildSummaryPrompt(string $conversationText): string
    {
        return <<<PROMPT
Summarize the following conversation concisely. Capture the key topics discussed, any decisions made, important facts mentioned, and the overall context. The summary will be used to provide context for future messages in this conversation.

Keep the summary under 500 words. Focus on information that would be useful for continuing the conversation.

--- CONVERSATION ---
{$conversationText}
--- END ---

Provide only the summary, no preamble or explanation.
PROMPT;
    }
}
