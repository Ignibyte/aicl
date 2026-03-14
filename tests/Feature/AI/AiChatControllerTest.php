<?php

namespace Aicl\Tests\Feature\AI;

use Aicl\AI\Jobs\AiConversationStreamJob;
use Aicl\Enums\AiProvider;
use Aicl\Models\AiAgent;
use Aicl\Models\AiConversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Laravel\Passport\Passport;
use Tests\TestCase;

class AiChatControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $otherUser;

    private AiAgent $agent;

    private AiConversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'Aicl\Database\Seeders\RoleSeeder']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->otherUser = User::factory()->create();
        $this->otherUser->assignRole('viewer');

        $this->agent = AiAgent::factory()->active()->create([
            'provider' => AiProvider::OpenAi,
            'model' => 'gpt-4o',
        ]);

        $this->conversation = AiConversation::factory()->create([
            'user_id' => $this->admin->id,
            'ai_agent_id' => $this->agent->id,
        ]);

        config(['aicl.ai.openai.api_key' => 'sk-test-key']);

        Bus::fake([AiConversationStreamJob::class]);
    }

    // ─── Happy Path ─────────────────────────────────────────────

    public function test_send_chat_message_returns_stream_info(): void
    {
        Passport::actingAs($this->admin, ['*']);

        $response = $this->postJson("/api/v1/ai-conversations/{$this->conversation->id}/chat", [
            'message' => 'Hello AI',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'stream_id',
                'channel',
                'message_id',
            ]);
    }

    public function test_send_chat_creates_user_message(): void
    {
        Passport::actingAs($this->admin, ['*']);

        $this->postJson("/api/v1/ai-conversations/{$this->conversation->id}/chat", [
            'message' => 'What is PHP?',
        ]);

        $this->assertDatabaseHas('ai_messages', [
            'ai_conversation_id' => $this->conversation->id,
            'role' => 'user',
            'content' => 'What is PHP?',
        ]);
    }

    public function test_send_chat_dispatches_stream_job(): void
    {
        Passport::actingAs($this->admin, ['*']);

        $this->postJson("/api/v1/ai-conversations/{$this->conversation->id}/chat", [
            'message' => 'Hello',
        ]);

        Bus::assertDispatched(AiConversationStreamJob::class, function (AiConversationStreamJob $job): bool {
            return $job->conversationId === $this->conversation->id;
        });
    }

    // ─── Validation ─────────────────────────────────────────────

    public function test_send_chat_requires_message(): void
    {
        Passport::actingAs($this->admin, ['*']);

        $response = $this->postJson("/api/v1/ai-conversations/{$this->conversation->id}/chat", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['message']);
    }

    public function test_send_chat_validates_message_max_length(): void
    {
        Passport::actingAs($this->admin, ['*']);
        config(['aicl.ai.max_prompt_length' => 10]);

        $response = $this->postJson("/api/v1/ai-conversations/{$this->conversation->id}/chat", [
            'message' => str_repeat('a', 11),
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['message']);
    }

    // ─── Authorization ──────────────────────────────────────────

    public function test_send_chat_requires_authentication(): void
    {
        $response = $this->postJson("/api/v1/ai-conversations/{$this->conversation->id}/chat", [
            'message' => 'Hello',
        ]);

        $response->assertUnauthorized();
    }

    public function test_send_chat_forbidden_for_non_owner(): void
    {
        Passport::actingAs($this->otherUser, ['*']);

        $response = $this->postJson("/api/v1/ai-conversations/{$this->conversation->id}/chat", [
            'message' => 'Hello',
        ]);

        $response->assertForbidden();
    }

    // ─── Error Cases ────────────────────────────────────────────

    public function test_send_chat_returns_422_when_agent_inactive(): void
    {
        Passport::actingAs($this->admin, ['*']);

        $inactiveAgent = AiAgent::factory()->create(['is_active' => false]);
        $conversation = AiConversation::factory()->create([
            'user_id' => $this->admin->id,
            'ai_agent_id' => $inactiveAgent->id,
        ]);

        $response = $this->postJson("/api/v1/ai-conversations/{$conversation->id}/chat", [
            'message' => 'Hello',
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure(['error']);
    }

    public function test_send_chat_returns_422_when_provider_not_configured(): void
    {
        Passport::actingAs($this->admin, ['*']);
        config(['aicl.ai.openai.api_key' => null]);

        $response = $this->postJson("/api/v1/ai-conversations/{$this->conversation->id}/chat", [
            'message' => 'Hello',
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure(['error']);
    }
}
