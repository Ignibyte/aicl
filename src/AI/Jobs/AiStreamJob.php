<?php

declare(strict_types=1);

namespace Aicl\AI\Jobs;

use Aicl\AI\AiProviderFactory;
use Aicl\AI\Events\AiStreamCompleted;
use Aicl\AI\Events\AiStreamFailed;
use Aicl\AI\Events\AiStreamStarted;
use Aicl\AI\Events\AiTokenEvent;
use Aicl\AI\Events\AiToolCallEvent;
use Generator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use NeuronAI\Agent\Agent;
use NeuronAI\Agent\AgentInterface;
use NeuronAI\Chat\Enums\MessageRole;
use NeuronAI\Chat\Messages\Message;
use NeuronAI\Chat\Messages\Stream\Chunks\TextChunk;
use NeuronAI\Chat\Messages\Stream\Chunks\ToolCallChunk;
use NeuronAI\Providers\AIProviderInterface;
use Throwable;

/** Queued job that streams standalone AI agent responses via WebSocket broadcast events. */
class AiStreamJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 1;

    public int $timeout = 120;

    private bool $decremented = false;

    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        public string $streamId,
        public int $userId,
        public string $prompt,
        public ?string $systemPrompt = null,
        public array $context = [],
        public ?string $driver = null,
    ) {
        $this->timeout = (int) config('aicl.ai.streaming.timeout', 120);
        $this->onQueue(config('aicl.ai.streaming.queue', 'default'));
    }

    public function handle(): void
    {
        broadcast(new AiStreamStarted($this->streamId, $this->userId));

        try {
            $provider = AiProviderFactory::make($this->driver);

            if (! $provider) {
                broadcast(new AiStreamFailed(
                    $this->streamId,
                    $this->userId,
                    'AI provider not available.',
                ));

                return;
            }

            $agent = $this->buildAgent($provider);

            $messages = $this->buildMessages();
            $index = 0;

            $handler = $agent->stream($messages);
            $generator = $handler->events();

            foreach ($generator as $chunk) {
                if ($chunk instanceof ToolCallChunk) {
                    broadcast(new AiToolCallEvent(
                        $this->streamId,
                        $this->userId,
                        [['name' => $chunk->tool->getName(), 'inputs' => $chunk->tool->getInputs()]],
                    ));

                    continue;
                }

                if ($chunk instanceof TextChunk) {
                    broadcast(new AiTokenEvent(
                        $this->streamId,
                        $this->userId,
                        $chunk->content,
                        $index++,
                    ));
                }
            }

            $usage = $this->extractUsage($generator);

            broadcast(new AiStreamCompleted(
                $this->streamId,
                $this->userId,
                $index,
                $usage,
            ));

            Log::info('AI stream completed', [
                'stream_id' => $this->streamId,
                'user_id' => $this->userId,
                'total_tokens' => $index,
                'usage' => $usage,
            ]);
            // @codeCoverageIgnoreStart — Job processing
        } catch (Throwable $e) {
            Log::error('AI stream failed', [
                'stream_id' => $this->streamId,
                'user_id' => $this->userId,
                'error' => $e->getMessage(),
            ]);

            broadcast(new AiStreamFailed(
                $this->streamId,
                $this->userId,
                'An error occurred while generating the response.',
            ));
            // @codeCoverageIgnoreEnd
        } finally {
            $this->decrementConcurrentCount();
        }
    }

    /**
     * Build the NeuronAI agent with the configured provider.
     *
     * Tools are intentionally disabled on this legacy endpoint — it has no
     * agent context to scope tool access. Use the conversation-based
     * AiConversationStreamJob for tool-enabled interactions.
     */
    protected function buildAgent(AIProviderInterface $provider): AgentInterface
    {
        return Agent::make()
            ->setAiProvider($provider)
            ->setInstructions($this->systemPrompt ?? config('aicl.ai.system_prompt', ''));
    }

    /**
     * Build messages array from the prompt and context.
     *
     * @return array<int, Message>
     */
    private function buildMessages(): array
    {
        $messages = [];

        if (! empty($this->context)) {
            $contextText = collect($this->context)
                ->map(fn (mixed $value, string $key): string => "{$key}: {$value}")
                ->implode("\n");

            $messages[] = new Message(
                MessageRole::USER,
                "Context:\n{$contextText}",
            );
        }

        $messages[] = new Message(MessageRole::USER, $this->prompt);

        return $messages;
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
     * Handle permanent job failure (safety net for worker crashes).
     */
    public function failed(Throwable $exception): void
    {
        $this->decrementConcurrentCount();

        Log::error('AI stream job failed permanently', [
            'stream_id' => $this->streamId,
            'user_id' => $this->userId,
            'error' => $exception->getMessage(),
        ]);
    }

    /**
     * Decrement the concurrent stream count for this user.
     *
     * Uses atomic Cache::decrement() to prevent TOCTOU race conditions
     * under Swoole concurrency. Floors at zero to prevent negative drift.
     */
    private function decrementConcurrentCount(): void
    {
        if ($this->decremented) {
            return;
        }

        $this->decremented = true;

        $key = "ai-stream:user:{$this->userId}:count";
        $newValue = (int) Cache::decrement($key);

        if ($newValue < 0) {
            Cache::put($key, 0, 300);
        }
    }
}
