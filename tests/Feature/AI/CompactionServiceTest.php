<?php

namespace Aicl\Tests\Feature\AI;

use Aicl\AI\CompactionService;
use Aicl\Enums\AiProvider;
use Aicl\Models\AiAgent;
use Aicl\Models\AiConversation;
use Aicl\Models\AiMessage;
use Aicl\States\AiConversation\Summarized;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class CompactionServiceTest extends TestCase
{
    use RefreshDatabase;

    private CompactionService $service;

    private User $user;

    private AiAgent $agent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(CompactionService::class);
        $this->user = User::factory()->create();
        $this->agent = AiAgent::factory()->active()->create([
            'provider' => AiProvider::OpenAi,
            'model' => 'gpt-4o',
            'context_messages' => 5,
        ]);

        config([
            'aicl.ai.openai.api_key' => 'sk-test',
            'aicl.ai.assistant.compaction_threshold' => 10,
        ]);
    }

    public function test_compact_throws_when_not_compactable(): void
    {
        $conversation = AiConversation::factory()->create([
            'user_id' => $this->user->id,
            'ai_agent_id' => $this->agent->id,
            'message_count' => 5,
            'summary' => null,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('not eligible for compaction');

        $this->service->compact($conversation);
    }

    public function test_compact_throws_when_already_summarized(): void
    {
        $conversation = AiConversation::factory()->create([
            'user_id' => $this->user->id,
            'ai_agent_id' => $this->agent->id,
            'message_count' => 100,
            'summary' => 'Already summarized.',
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('not eligible for compaction');

        $this->service->compact($conversation);
    }

    public function test_compact_throws_when_provider_not_configured(): void
    {
        config(['aicl.ai.openai.api_key' => null]);

        $conversation = $this->createCompactableConversation();

        $this->expectException(\RuntimeException::class);

        $this->service->compact($conversation);
    }

    public function test_compact_stores_summary_on_conversation(): void
    {
        $conversation = $this->createCompactableConversation();
        $expectedSummary = 'The user asked about PHP and the assistant explained it.';

        $this->useCompactionServiceMock($expectedSummary);

        $this->service->compact($conversation);

        $conversation->refresh();
        $this->assertEquals($expectedSummary, $conversation->summary);
    }

    public function test_compact_transitions_state_to_summarized(): void
    {
        $conversation = $this->createCompactableConversation();

        $this->useCompactionServiceMock('Summary of conversation.');

        $this->service->compact($conversation);

        $conversation->refresh();
        $this->assertInstanceOf(Summarized::class, $conversation->state);
    }

    public function test_compact_does_not_delete_old_messages_by_default(): void
    {
        $conversation = $this->createCompactableConversation();
        $initialCount = $conversation->messages()->count();

        $this->useCompactionServiceMock('Summary of conversation.');

        $this->service->compact($conversation);

        $this->assertEquals($initialCount, $conversation->messages()->count());
    }

    public function test_compact_deletes_old_messages_when_configured(): void
    {
        config(['aicl.ai.assistant.compaction_delete_old_messages' => true]);

        $conversation = $this->createCompactableConversation();

        $this->useCompactionServiceMock('Summary of conversation.');

        $this->service->compact($conversation);

        // Should only have context_messages (5) remaining
        $this->assertEquals(5, $conversation->messages()->count());
    }

    public function test_compact_is_no_longer_compactable_after(): void
    {
        $conversation = $this->createCompactableConversation();

        $this->useCompactionServiceMock('Summary text.');

        $this->service->compact($conversation);

        $conversation->refresh();
        $this->assertFalse($conversation->is_compactable);
    }

    // ─── Helpers ────────────────────────────────────────────────

    private function createCompactableConversation(): AiConversation
    {
        $conversation = AiConversation::factory()->create([
            'user_id' => $this->user->id,
            'ai_agent_id' => $this->agent->id,
            'message_count' => 0,
            'token_count' => 0,
            'summary' => null,
        ]);

        // Create 15 messages (above threshold of 10)
        for ($i = 1; $i <= 15; $i++) {
            AiMessage::factory()->create([
                'ai_conversation_id' => $conversation->id,
                'role' => $i % 2 === 1 ? 'user' : 'assistant',
                'content' => "Message {$i}",
            ]);
        }

        $conversation->refresh();

        return $conversation;
    }

    /**
     * Create a partial mock of CompactionService that stubs the AI call.
     *
     * The summarizeWithAi method is protected and makes the actual NeuronAI call.
     * We stub it to avoid real HTTP requests while testing the compaction logic.
     */
    private function useCompactionServiceMock(string $summaryResponse): void
    {
        $mock = Mockery::mock(CompactionService::class)
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();

        $mock->shouldReceive('summarizeWithAi')
            ->once()
            ->andReturn($summaryResponse);

        $this->app->instance(CompactionService::class, $mock);
        $this->service = $mock;
    }
}
