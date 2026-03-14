<?php

namespace Aicl\Tests\Feature\AI;

use Aicl\AI\AiChatService;
use Aicl\AI\Jobs\AiConversationStreamJob;
use Aicl\Enums\AiProvider;
use Aicl\Models\AiAgent;
use Aicl\Models\AiConversation;
use Aicl\Models\AiMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AiChatServiceTest extends TestCase
{
    use RefreshDatabase;

    private AiChatService $service;

    private User $user;

    private AiAgent $agent;

    private AiConversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(AiChatService::class);
        $this->user = User::factory()->create();
        $this->agent = AiAgent::factory()->active()->create([
            'provider' => AiProvider::OpenAi,
            'model' => 'gpt-4o',
            'context_messages' => 10,
            'max_tokens' => 4096,
        ]);
        $this->conversation = AiConversation::factory()->create([
            'user_id' => $this->user->id,
            'ai_agent_id' => $this->agent->id,
        ]);

        Bus::fake([AiConversationStreamJob::class]);
    }

    // ─── sendMessage ────────────────────────────────────────────

    public function test_send_message_creates_user_message(): void
    {
        config(['aicl.ai.openai.api_key' => 'sk-test']);

        $result = $this->service->sendMessage($this->conversation, 'Hello AI');

        $this->assertDatabaseHas('ai_messages', [
            'ai_conversation_id' => $this->conversation->id,
            'role' => 'user',
            'content' => 'Hello AI',
        ]);
    }

    public function test_send_message_returns_stream_info(): void
    {
        config(['aicl.ai.openai.api_key' => 'sk-test']);

        $result = $this->service->sendMessage($this->conversation, 'Hello');

        $this->assertArrayHasKey('stream_id', $result);
        $this->assertArrayHasKey('channel', $result);
        $this->assertArrayHasKey('message_id', $result);
        $this->assertStringStartsWith('private-ai.stream.', $result['channel']);
    }

    public function test_send_message_dispatches_conversation_stream_job(): void
    {
        config(['aicl.ai.openai.api_key' => 'sk-test']);

        $result = $this->service->sendMessage($this->conversation, 'Test prompt');

        Bus::assertDispatched(AiConversationStreamJob::class, function (AiConversationStreamJob $job) use ($result): bool {
            return $job->streamId === $result['stream_id']
                && $job->conversationId === $this->conversation->id;
        });
    }

    public function test_send_message_stores_stream_user_in_cache(): void
    {
        config(['aicl.ai.openai.api_key' => 'sk-test']);

        $result = $this->service->sendMessage($this->conversation, 'Hello');

        $cachedUserId = Cache::get("ai-stream:{$result['stream_id']}:user");
        $this->assertEquals($this->user->id, $cachedUserId);
    }

    public function test_send_message_throws_when_agent_inactive(): void
    {
        $inactiveAgent = AiAgent::factory()->create(['is_active' => false]);
        $conversation = AiConversation::factory()->create([
            'user_id' => $this->user->id,
            'ai_agent_id' => $inactiveAgent->id,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('AI agent is not available or not configured.');

        $this->service->sendMessage($conversation, 'Hello');
    }

    public function test_send_message_throws_when_provider_not_configured(): void
    {
        config(['aicl.ai.openai.api_key' => null]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('AI agent is not available or not configured.');

        $this->service->sendMessage($this->conversation, 'Hello');
    }

    public function test_send_message_throws_on_concurrent_limit(): void
    {
        config([
            'aicl.ai.openai.api_key' => 'sk-test',
            'aicl.ai.streaming.max_concurrent_per_user' => 2,
        ]);

        Cache::put("ai-stream:user:{$this->user->id}:count", 2, 300);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Too many concurrent AI streams.');

        $this->service->sendMessage($this->conversation, 'Hello');
    }

    public function test_send_message_increments_concurrent_count(): void
    {
        config(['aicl.ai.openai.api_key' => 'sk-test']);

        Cache::put("ai-stream:user:{$this->user->id}:count", 0, 300);

        $this->service->sendMessage($this->conversation, 'Hello');

        $this->assertEquals(1, (int) Cache::get("ai-stream:user:{$this->user->id}:count"));
    }

    // ─── buildMessageHistory ────────────────────────────────────

    public function test_build_message_history_returns_recent_messages(): void
    {
        AiMessage::factory()->fromUser()->create([
            'ai_conversation_id' => $this->conversation->id,
            'content' => 'First message',
        ]);
        AiMessage::factory()->fromAssistant()->create([
            'ai_conversation_id' => $this->conversation->id,
            'content' => 'First response',
        ]);

        $history = $this->service->buildMessageHistory($this->conversation);

        $this->assertCount(2, $history);
    }

    public function test_build_message_history_respects_context_messages_limit(): void
    {
        $agent = AiAgent::factory()->active()->create([
            'context_messages' => 3,
        ]);
        $conversation = AiConversation::factory()->create([
            'user_id' => $this->user->id,
            'ai_agent_id' => $agent->id,
        ]);

        // Create 5 messages
        for ($i = 1; $i <= 5; $i++) {
            AiMessage::factory()->fromUser()->create([
                'ai_conversation_id' => $conversation->id,
                'content' => "Message {$i}",
            ]);
        }

        $history = $this->service->buildMessageHistory($conversation);

        // Should only return 3 (context_messages limit)
        $this->assertCount(3, $history);
    }

    public function test_build_message_history_includes_summary_as_system_message(): void
    {
        $this->conversation->update(['summary' => 'Previously discussed project setup.']);

        AiMessage::factory()->fromUser()->create([
            'ai_conversation_id' => $this->conversation->id,
            'content' => 'Continue from where we left off',
        ]);

        $history = $this->service->buildMessageHistory($this->conversation->refresh());

        // Summary (system) + 1 user message
        $this->assertCount(2, $history);
        $this->assertStringContainsString('Previously discussed project setup', (string) $history[0]->getContent());
    }

    public function test_build_message_history_returns_empty_for_new_conversation(): void
    {
        $history = $this->service->buildMessageHistory($this->conversation);

        $this->assertCount(0, $history);
    }

    public function test_build_message_history_preserves_message_order(): void
    {
        $msg1 = AiMessage::factory()->fromUser()->create([
            'ai_conversation_id' => $this->conversation->id,
            'content' => 'First',
            'created_at' => now()->subMinutes(2),
        ]);
        $msg2 = AiMessage::factory()->fromAssistant()->create([
            'ai_conversation_id' => $this->conversation->id,
            'content' => 'Second',
            'created_at' => now()->subMinute(),
        ]);
        $msg3 = AiMessage::factory()->fromUser()->create([
            'ai_conversation_id' => $this->conversation->id,
            'content' => 'Third',
            'created_at' => now(),
        ]);

        $history = $this->service->buildMessageHistory($this->conversation);

        $this->assertCount(3, $history);
        $this->assertEquals('First', $history[0]->getContent());
        $this->assertEquals('Second', $history[1]->getContent());
        $this->assertEquals('Third', $history[2]->getContent());
    }
}
