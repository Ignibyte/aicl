<?php

namespace Aicl\AI\Jobs;

use Aicl\AI\AiProviderFactory;
use Aicl\AI\Events\AiStreamCompleted;
use Aicl\AI\Events\AiStreamFailed;
use Aicl\AI\Events\AiStreamStarted;
use Aicl\AI\Events\AiTokenEvent;
use Aicl\AI\Events\AiToolCallEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use NeuronAI\Agent;
use NeuronAI\AgentInterface;
use NeuronAI\Chat\Enums\MessageRole;
use NeuronAI\Chat\Messages\Message;
use NeuronAI\Chat\Messages\ToolCallMessage;
use NeuronAI\Providers\AIProviderInterface;

class AiStreamJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 1;

    public int $timeout = 120;

    /**
     * @param  array<string, mixed>  $context
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

            $generator = $agent->stream($messages);

            foreach ($generator as $chunk) {
                if ($chunk instanceof ToolCallMessage) {
                    broadcast(new AiToolCallEvent(
                        $this->streamId,
                        $this->userId,
                        collect($chunk->getTools())->map(fn ($t): array => [
                            'name' => $t->getName(),
                            'inputs' => $t->getInputs(),
                        ])->toArray(),
                    ));

                    continue;
                }

                broadcast(new AiTokenEvent(
                    $this->streamId,
                    $this->userId,
                    (string) $chunk,
                    $index++,
                ));
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
        } catch (\Throwable $e) {
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
    private function extractUsage(\Generator $generator): array
    {
        $response = $generator->getReturn();

        if ($response && method_exists($response, 'getUsage') && $response->getUsage()) {
            return [
                'input_tokens' => $response->getUsage()->inputTokens,
                'output_tokens' => $response->getUsage()->outputTokens,
            ];
        }

        return [];
    }

    /**
     * Decrement the concurrent stream count for this user.
     */
    private function decrementConcurrentCount(): void
    {
        $key = "ai-stream:user:{$this->userId}:count";
        $current = (int) Cache::get($key, 0);

        if ($current > 0) {
            Cache::put($key, $current - 1, 300);
        }
    }
}
