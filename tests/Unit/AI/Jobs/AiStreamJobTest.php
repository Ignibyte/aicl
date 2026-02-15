<?php

namespace Aicl\Tests\Unit\AI\Jobs;

use Aicl\AI\Events\AiStreamCompleted;
use Aicl\AI\Events\AiStreamFailed;
use Aicl\AI\Events\AiStreamStarted;
use Aicl\AI\Events\AiTokenEvent;
use Aicl\AI\Jobs\AiStreamJob;
use Generator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use NeuronAI\AgentInterface;
use NeuronAI\Chat\Messages\AssistantMessage;
use NeuronAI\Chat\Messages\Usage;
use NeuronAI\Providers\AIProviderInterface;
use Tests\TestCase;

class AiStreamJobTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Event::fake();
    }

    public function test_job_is_queueable(): void
    {
        $job = new AiStreamJob('stream-1', 1, 'Hello');

        $this->assertInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class, $job);
    }

    public function test_tries_is_one(): void
    {
        $job = new AiStreamJob('stream-1', 1, 'Hello');

        $this->assertSame(1, $job->tries);
    }

    public function test_broadcasts_started_event(): void
    {
        config(['aicl.ai.provider' => 'openai', 'aicl.ai.openai.api_key' => 'sk-test']);

        $job = $this->makeStreamingJob('stream-1', 1, 'test prompt', ['Hello', ' world']);
        $job->handle();

        Event::assertDispatched(AiStreamStarted::class, function (AiStreamStarted $event): bool {
            return $event->streamId === 'stream-1' && $event->userId === 1;
        });
    }

    public function test_broadcasts_token_events_for_each_chunk(): void
    {
        config(['aicl.ai.provider' => 'openai', 'aicl.ai.openai.api_key' => 'sk-test']);

        $job = $this->makeStreamingJob('stream-1', 1, 'test prompt', ['Hello', ' ', 'world']);
        $job->handle();

        Event::assertDispatched(AiTokenEvent::class, 3);

        Event::assertDispatched(AiTokenEvent::class, function (AiTokenEvent $event): bool {
            return $event->streamId === 'stream-1'
                && $event->token === 'Hello'
                && $event->index === 0;
        });

        Event::assertDispatched(AiTokenEvent::class, function (AiTokenEvent $event): bool {
            return $event->token === 'world' && $event->index === 2;
        });
    }

    public function test_broadcasts_completed_event_with_token_count(): void
    {
        config(['aicl.ai.provider' => 'openai', 'aicl.ai.openai.api_key' => 'sk-test']);

        $job = $this->makeStreamingJob('stream-1', 1, 'test prompt', ['a', 'b', 'c']);
        $job->handle();

        Event::assertDispatched(AiStreamCompleted::class, function (AiStreamCompleted $event): bool {
            return $event->streamId === 'stream-1'
                && $event->totalTokens === 3
                && $event->userId === 1;
        });
    }

    public function test_broadcasts_failed_event_on_exception(): void
    {
        config(['aicl.ai.provider' => 'openai', 'aicl.ai.openai.api_key' => 'sk-test']);

        Log::shouldReceive('error')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                return $message === 'AI stream failed'
                    && $context['stream_id'] === 'stream-1'
                    && str_contains($context['error'], 'LLM timeout');
            });

        $job = $this->makeThrowingJob('stream-1', 1, 'test prompt', new \RuntimeException('LLM timeout'));
        $job->handle();

        Event::assertDispatched(AiStreamStarted::class);
        Event::assertDispatched(AiStreamFailed::class, function (AiStreamFailed $event): bool {
            return $event->streamId === 'stream-1'
                && $event->error === 'An error occurred while generating the response.';
        });
        Event::assertNotDispatched(AiStreamCompleted::class);
    }

    public function test_broadcasts_failed_when_provider_unavailable(): void
    {
        config(['aicl.ai.provider' => 'openai', 'aicl.ai.openai.api_key' => null]);

        $job = new AiStreamJob('stream-1', 1, 'test prompt');
        $job->handle();

        Event::assertDispatched(AiStreamStarted::class);
        Event::assertDispatched(AiStreamFailed::class, function (AiStreamFailed $event): bool {
            return $event->error === 'AI provider not available.';
        });
        Event::assertNotDispatched(AiTokenEvent::class);
    }

    public function test_decrements_concurrent_count_on_success(): void
    {
        config(['aicl.ai.provider' => 'openai', 'aicl.ai.openai.api_key' => 'sk-test']);

        Cache::put('ai-stream:user:1:count', 2, 300);

        $job = $this->makeStreamingJob('stream-1', 1, 'test prompt', ['token']);
        $job->handle();

        $this->assertSame(1, (int) Cache::get('ai-stream:user:1:count'));
    }

    public function test_decrements_concurrent_count_on_failure(): void
    {
        config(['aicl.ai.provider' => 'openai', 'aicl.ai.openai.api_key' => 'sk-test']);

        Cache::put('ai-stream:user:1:count', 1, 300);

        Log::shouldReceive('error')->once();

        $job = $this->makeThrowingJob('stream-1', 1, 'test prompt', new \RuntimeException('Boom'));
        $job->handle();

        $this->assertSame(0, (int) Cache::get('ai-stream:user:1:count'));
    }

    public function test_does_not_decrement_below_zero(): void
    {
        config(['aicl.ai.provider' => 'openai', 'aicl.ai.openai.api_key' => 'sk-test']);

        Cache::forget('ai-stream:user:1:count');

        $job = $this->makeStreamingJob('stream-1', 1, 'test prompt', ['token']);
        $job->handle();

        $this->assertSame(0, (int) Cache::get('ai-stream:user:1:count', 0));
    }

    public function test_uses_configured_queue(): void
    {
        config(['aicl.ai.streaming.queue' => 'ai-processing']);

        $job = new AiStreamJob('stream-1', 1, 'Hello');

        $this->assertSame('ai-processing', $job->queue);
    }

    public function test_constructor_sets_all_properties(): void
    {
        $job = new AiStreamJob(
            streamId: 'abc-123',
            userId: 42,
            prompt: 'Tell me a joke',
            systemPrompt: 'Be funny',
            context: ['entity' => 'Task'],
            driver: 'anthropic',
        );

        $this->assertSame('abc-123', $job->streamId);
        $this->assertSame(42, $job->userId);
        $this->assertSame('Tell me a joke', $job->prompt);
        $this->assertSame('Be funny', $job->systemPrompt);
        $this->assertSame(['entity' => 'Task'], $job->context);
        $this->assertSame('anthropic', $job->driver);
    }

    public function test_logs_info_on_successful_stream(): void
    {
        config(['aicl.ai.provider' => 'openai', 'aicl.ai.openai.api_key' => 'sk-test']);

        Log::shouldReceive('info')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                return $message === 'AI stream completed'
                    && $context['stream_id'] === 'stream-1'
                    && $context['total_tokens'] === 2;
            });

        $job = $this->makeStreamingJob('stream-1', 1, 'prompt', ['Hi', '!']);
        $job->handle();
    }

    /**
     * Create a job with a mock agent that streams the given tokens.
     *
     * @param  array<int, string>  $tokens
     */
    private function makeStreamingJob(string $streamId, int $userId, string $prompt, array $tokens): AiStreamJob
    {
        $usage = new Usage(10, count($tokens));
        $response = new AssistantMessage(implode('', $tokens));
        $response->setUsage($usage);

        $agent = \Mockery::mock(AgentInterface::class);
        $agent->shouldReceive('stream')->once()->andReturnUsing(function () use ($tokens, $response): Generator {
            foreach ($tokens as $token) {
                yield $token;
            }

            return $response;
        });

        return new class($streamId, $userId, $prompt, $agent) extends AiStreamJob
        {
            private AgentInterface $mockAgent;

            public function __construct(string $streamId, int $userId, string $prompt, AgentInterface $mockAgent)
            {
                parent::__construct($streamId, $userId, $prompt);
                $this->mockAgent = $mockAgent;
            }

            protected function buildAgent(AIProviderInterface $provider): AgentInterface
            {
                return $this->mockAgent;
            }
        };
    }

    /**
     * Create a job with a mock agent that throws on stream.
     */
    private function makeThrowingJob(string $streamId, int $userId, string $prompt, \Throwable $exception): AiStreamJob
    {
        $agent = \Mockery::mock(AgentInterface::class);
        $agent->shouldReceive('stream')->once()->andThrow($exception);

        return new class($streamId, $userId, $prompt, $agent) extends AiStreamJob
        {
            private AgentInterface $mockAgent;

            public function __construct(string $streamId, int $userId, string $prompt, AgentInterface $mockAgent)
            {
                parent::__construct($streamId, $userId, $prompt);
                $this->mockAgent = $mockAgent;
            }

            protected function buildAgent(AIProviderInterface $provider): AgentInterface
            {
                return $this->mockAgent;
            }
        };
    }
}
