<?php

declare(strict_types=1);

namespace Aicl\AI\Jobs;

use Aicl\AI\AiChatService;
use Aicl\AI\AiProviderFactory;
use Aicl\AI\AiToolRegistry;
use Aicl\AI\Events\AiStreamCompleted;
use Aicl\AI\Events\AiStreamFailed;
use Aicl\AI\Events\AiStreamStarted;
use Aicl\AI\Events\AiTokenEvent;
use Aicl\AI\Events\AiToolCallEvent;
use Aicl\AI\Tools\BaseTool;
use Aicl\Enums\AiMessageRole;
use Aicl\Models\AiAgent;
use Aicl\Models\AiConversation;
use Generator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use NeuronAI\Agent\Agent;
use NeuronAI\Agent\AgentInterface;
use NeuronAI\Chat\Messages\Stream\Chunks\TextChunk;
use NeuronAI\Chat\Messages\Stream\Chunks\ToolCallChunk;
use NeuronAI\Providers\AIProviderInterface;
use Throwable;
use TypeError;

/** Queued job that streams AI conversation responses via WebSocket broadcast events. */
class AiConversationStreamJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 1;

    public int $timeout = 120;

    private bool $decremented = false;

    public function __construct(
        public string $streamId,
        public string $conversationId,
        public int $userId,
    ) {
        $this->timeout = (int) config('aicl.ai.streaming.timeout', 120);
        $this->onQueue(config('aicl.ai.streaming.queue', 'default'));
    }

    /**
     * Resolve the conversation and agent, broadcasting failure if not found.
     *
     * @codeCoverageIgnore Reason: mcp-runtime -- AI streaming job requires NeuronAI provider, WebSocket, and conversation context
     */
    private function findConversation(): ?AiConversation
    {
        $conversation = AiConversation::with('agent')->find($this->conversationId);

        if (! $conversation || ! $conversation->agent) {
            Log::error('AI conversation stream: conversation or agent not found', [
                'stream_id' => $this->streamId,
                'conversation_id' => $this->conversationId,
            ]);

            return null;
        }

        return $conversation;
    }

    /**
     * Build the tool entry array for a single tool call, resolving render data when available.
     *
     * @param array<string, mixed> $toolResults Accumulated tool results (passed by reference)
     *
     * @return array<string, mixed>
     *
     * @codeCoverageIgnore Reason: mcp-runtime -- AI streaming job requires NeuronAI provider, WebSocket, and conversation context
     */
    private function buildToolEntry(mixed $t, array &$toolResults): array
    {
        $entry = [
            'name' => $t->getName(),
            'inputs' => $t->getInputs(),
        ];

        if ($t instanceof BaseTool) {
            try {
                $resultStr = $t->getResult();
                $rawResult = json_decode($resultStr, true) ?? $resultStr;
            } catch (TypeError) {
                $rawResult = null;
            }

            if ($rawResult !== null) {
                $entry['render'] = $t->formatResultForDisplay($rawResult);
            }

            $toolResults[] = $entry;
        }

        return $entry;
    }

    /**
     * Extract usage data from the completed generator.
     *
     * @return array<string, int>
     */
    private function extractUsage(Generator $generator): array
    {
        /** @var object|null $response */
        $response = $generator->getReturn();

        if (is_object($response) && method_exists($response, 'getUsage') && $response->getUsage()) {
            $usage = $response->getUsage();

            return [
                'input_tokens' => $usage->inputTokens,
                'output_tokens' => $usage->outputTokens,
            ];
        }

        return [];
    }

    /**
     * Strip leading tool call JSON from the response text.
     *
     * NeuronAI may include tool call+result data as a JSON array
     * at the start of the text stream (e.g., [{...}]Natural language).
     * This removes the JSON portion and returns the clean text.
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    private function stripToolCallJson(string $response): string
    {
        $trimmed = trim($response);

        if (! str_starts_with($trimmed, '[{')) {
            return $response;
        }

        // Find closing bracket by tracking bracket depth
        $depth = 0;
        $inString = false;
        $escape = false;
        $len = strlen($trimmed);

        for ($i = 0; $i < $len; $i++) {
            $char = $trimmed[$i];

            if ($escape) {
                $escape = false;

                continue;
            }

            if ($char === '\\' && $inString) {
                $escape = true;

                continue;
            }

            if ($char === '"') {
                $inString = ! $inString;

                continue;
            }

            if ($inString) {
                continue;
            }

            if ($char === '[') {
                $depth++;
            }

            if ($char === ']') {
                $depth--;

                if ($depth === 0) {
                    $jsonStr = substr($trimmed, 0, $i + 1);
                    $decoded = json_decode($jsonStr, true);

                    // Only strip if it's a valid tool call array
                    if (is_array($decoded) && ! empty($decoded) && isset($decoded[0]['name'])) {
                        $remaining = trim(substr($trimmed, $i + 1));

                        return $remaining !== '' ? $remaining : $response;
                    }

                    return $response;
                }
            }
        }

        return $response;
    }

    /**
     * Decrement the concurrent stream count for this user.
     *
     * Uses atomic Cache::decrement() to prevent TOCTOU race conditions
     * under Swoole concurrency. Floors at zero to prevent negative drift.
     */
    private function decrementConcurrentCount(int $userId): void
    {
        if ($this->decremented) {
            return;
        }

        $this->decremented = true;

        $key = "ai-stream:user:{$userId}:count";
        $newValue = (int) Cache::decrement($key);

        if ($newValue < 0) {
            Cache::put($key, 0, 300);
        }
    }

    /**
     * Run the streaming loop: iterate generator chunks, dispatch broadcast events,
     * persist the assistant message, and trigger auto-compaction when needed.
     *
     * @param array<mixed> $messages
     *
     * @codeCoverageIgnore Reason: mcp-runtime -- AI streaming job requires NeuronAI provider, WebSocket, and conversation context
     */
    private function runStream(AiConversation $conversation, AgentInterface $neuronAgent, array $messages, int $userId): void
    {
        $agent = $conversation->agent;
        $fullResponse = '';
        $index = 0;
        $toolResults = [];

        $handler = $neuronAgent->stream($messages);
        $generator = $handler->events();

        // @codeCoverageIgnoreStart — Streaming loop requires real AI provider connection
        foreach ($generator as $chunk) {
            if ($chunk instanceof ToolCallChunk) {
                $toolData = [$this->buildToolEntry($chunk->tool, $toolResults)];

                broadcast(new AiToolCallEvent($this->streamId, $userId, $toolData));

                continue;
            }

            if ($chunk instanceof TextChunk) {
                $fullResponse .= $chunk->content;

                broadcast(new AiTokenEvent($this->streamId, $userId, $chunk->content, $index++));
            }
        }

        $usage = $this->extractUsage($generator);
        // @codeCoverageIgnoreEnd

        $cleanResponse = $this->stripToolCallJson($fullResponse);
        $totalTokens = ($usage['input_tokens'] ?? 0) + ($usage['output_tokens'] ?? 0);

        // @codeCoverageIgnoreStart — Job processing
        $metadata = [
            'model' => $agent->model,
            'provider' => $agent->provider->value,
            'usage' => $usage,
            'stream_id' => $this->streamId,
        ];

        if (! empty($toolResults)) {
            $metadata['tool_results'] = $toolResults;
        }

        $conversation->messages()->create([
            'role' => AiMessageRole::Assistant,
            'content' => $cleanResponse,
            'token_count' => $totalTokens > 0 ? $totalTokens : null,
            'metadata' => $metadata,
        ]);

        broadcast(new AiStreamCompleted($this->streamId, $userId, $index, $usage));

        Log::info('AI conversation stream completed', [
            'stream_id' => $this->streamId,
            'conversation_id' => $this->conversationId,
            'agent' => $agent->slug,
            'total_tokens' => $index,
            'usage' => $usage,
        ]);

        $conversation->refresh();

        if ($conversation->is_compactable) {
            CompactConversationJob::dispatch($conversation->id);
        }
        // @codeCoverageIgnoreEnd
    }

    /**
     * Build the NeuronAI agent with the agent-specific provider and tools.
     *
     * Tool resolution respects the agent's capabilities:
     * - Global tools_enabled config must be true
     * - Agent must have capabilities.tools_enabled = true
     * - Only allowed_tools are attached (or all if unrestricted)
     *
     * @codeCoverageIgnore Requires real AI provider SDK — tool wiring tested via AiToolRegistry unit tests
     */
    protected function buildNeuronAgent(AIProviderInterface $provider, AiAgent $agent, int $userId): AgentInterface
    {
        $neuronAgent = Agent::make()
            ->setAiProvider($provider)
            ->setInstructions($agent->system_prompt ?? config('aicl.ai.system_prompt', ''));

        if (config('aicl.ai.tools_enabled', true)) {
            $registry = app(AiToolRegistry::class);
            $tools = $registry->resolveForAgent($agent, $userId);

            if (! empty($tools)) {
                $neuronAgent->addTool($tools);
            }
        }

        return $neuronAgent;
    }

    /**
     * @codeCoverageIgnore Reason: mcp-runtime -- AI streaming job requires NeuronAI provider, WebSocket, and conversation context
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function handle(AiChatService $chatService): void
    {
        $userId = $this->userId;

        try {
            $conversation = $this->findConversation();

            if (! $conversation) {
                return;
            }

            $agent = $conversation->agent;

            broadcast(new AiStreamStarted($this->streamId, $userId));
            $provider = AiProviderFactory::makeFromAgent($agent);

            if (! $provider) {
                broadcast(new AiStreamFailed(
                    $this->streamId,
                    $userId,
                    'AI provider not configured for this agent.',
                ));

                return;
            }

            $neuronAgent = $this->buildNeuronAgent($provider, $agent, $userId);
            $messages = $chatService->buildMessageHistory($conversation);

            $this->runStream($conversation, $neuronAgent, $messages, $userId);
        } catch (Throwable $e) {
            Log::error('AI conversation stream failed', [
                'stream_id' => $this->streamId,
                'conversation_id' => $this->conversationId,
                'error' => $e->getMessage(),
            ]);

            broadcast(new AiStreamFailed(
                $this->streamId,
                $userId,
                'An error occurred while generating the response.',
            ));
        } finally {
            $this->decrementConcurrentCount($userId);
        }
    }

    /**
     * Handle permanent job failure (safety net for worker crashes).
     *
     * If the worker is killed (SIGKILL, OOM) before finally runs,
     * Laravel calls this on a fresh instance after retry_after expires.
     * The $decremented flag prevents double-decrement in the normal
     * exception path where finally already ran.
     */
    public function failed(Throwable $exception): void
    {
        $this->decrementConcurrentCount($this->userId);

        Log::error('AI conversation stream job failed permanently', [
            'stream_id' => $this->streamId,
            'conversation_id' => $this->conversationId,
            'error' => $exception->getMessage(),
        ]);
    }
}
