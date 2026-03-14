<?php

namespace Aicl\Tests\Feature\AI;

use Aicl\AI\AiChatService;
use Aicl\AI\Events\AiStreamFailed;
use Aicl\AI\Events\AiStreamStarted;
use Aicl\AI\Jobs\AiConversationStreamJob;
use Aicl\Enums\AiProvider;
use Aicl\Models\AiAgent;
use Aicl\Models\AiConversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class AiConversationStreamJobTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private AiAgent $agent;

    private AiConversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->agent = AiAgent::factory()->active()->create([
            'provider' => AiProvider::OpenAi,
            'model' => 'gpt-4o',
            'system_prompt' => 'You are a helpful assistant.',
            'context_messages' => 10,
            'max_tokens' => 4096,
            'temperature' => '0.70',
        ]);
        $this->conversation = AiConversation::factory()->create([
            'user_id' => $this->user->id,
            'ai_agent_id' => $this->agent->id,
        ]);
    }

    public function test_job_can_be_instantiated(): void
    {
        $job = new AiConversationStreamJob(
            streamId: 'test-stream-id',
            conversationId: $this->conversation->id,
        );

        $this->assertEquals('test-stream-id', $job->streamId);
        $this->assertEquals($this->conversation->id, $job->conversationId);
        $this->assertEquals(1, $job->tries);
    }

    public function test_job_is_dispatched_to_correct_queue(): void
    {
        config(['aicl.ai.streaming.queue' => 'ai-streams']);

        $job = new AiConversationStreamJob(
            streamId: 'test-stream-id',
            conversationId: $this->conversation->id,
        );

        $this->assertEquals('ai-streams', $job->queue);
    }

    public function test_job_timeout_from_config(): void
    {
        config(['aicl.ai.streaming.timeout' => 60]);

        $job = new AiConversationStreamJob(
            streamId: 'test-stream-id',
            conversationId: $this->conversation->id,
        );

        $this->assertEquals(60, $job->timeout);
    }

    public function test_job_handles_missing_conversation_gracefully(): void
    {
        Event::fake();

        $job = new AiConversationStreamJob(
            streamId: 'test-stream-id',
            conversationId: '00000000-0000-0000-0000-000000000000',
        );

        // Should not throw — just returns early
        $job->handle(app(AiChatService::class));

        // No events broadcast for missing conversation
        Event::assertNotDispatched(AiStreamStarted::class);
    }

    public function test_job_broadcasts_failure_when_provider_not_configured(): void
    {
        Event::fake();
        config(['aicl.ai.openai.api_key' => null]);

        $job = new AiConversationStreamJob(
            streamId: 'test-stream-id',
            conversationId: $this->conversation->id,
        );

        $job->handle(app(AiChatService::class));

        Event::assertDispatched(AiStreamStarted::class);
        Event::assertDispatched(AiStreamFailed::class, function ($event): bool {
            return $event->streamId === 'test-stream-id'
                && str_contains($event->error, 'not configured');
        });
    }

    public function test_job_decrements_concurrent_count_on_failure(): void
    {
        config(['aicl.ai.openai.api_key' => null]);
        Event::fake();

        Cache::put("ai-stream:user:{$this->user->id}:count", 1, 300);

        $job = new AiConversationStreamJob(
            streamId: 'test-stream-id',
            conversationId: $this->conversation->id,
        );

        $job->handle(app(AiChatService::class));

        $this->assertEquals(0, (int) Cache::get("ai-stream:user:{$this->user->id}:count"));
    }
}
