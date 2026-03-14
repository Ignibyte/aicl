<?php

namespace Aicl\Tests\Feature\Livewire;

use Aicl\Enums\AiProvider;
use Aicl\Livewire\AiAssistantPanel;
use Aicl\Models\AiAgent;
use Aicl\Models\AiConversation;
use Aicl\Models\AiMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AiAssistantPanelTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private AiAgent $agent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->agent = AiAgent::factory()->active()->create([
            'provider' => AiProvider::OpenAi,
            'model' => 'gpt-4o',
        ]);

        config([
            'aicl.ai.openai.api_key' => 'sk-test',
            'aicl.ai.assistant.enabled' => true,
        ]);
    }

    public function test_component_can_be_rendered(): void
    {
        Livewire::actingAs($this->user)
            ->test(AiAssistantPanel::class)
            ->assertStatus(200);
    }

    public function test_agents_computed_returns_active_agents(): void
    {
        AiAgent::factory()->create(['is_active' => false]);

        $component = Livewire::actingAs($this->user)
            ->test(AiAssistantPanel::class);

        $agents = $component->instance()->agents();
        $this->assertCount(1, $agents);
        $this->assertEquals($this->agent->id, $agents->first()->id);
    }

    public function test_conversations_computed_returns_user_conversations(): void
    {
        $myConvo = AiConversation::factory()->create([
            'user_id' => $this->user->id,
            'ai_agent_id' => $this->agent->id,
        ]);

        $otherUser = User::factory()->create();
        AiConversation::factory()->create([
            'user_id' => $otherUser->id,
            'ai_agent_id' => $this->agent->id,
        ]);

        $component = Livewire::actingAs($this->user)
            ->test(AiAssistantPanel::class);

        $conversations = $component->instance()->conversations();
        $this->assertCount(1, $conversations);
        $this->assertEquals($myConvo->id, $conversations->first()->id);
    }

    public function test_create_conversation(): void
    {
        Livewire::actingAs($this->user)
            ->test(AiAssistantPanel::class)
            ->set('selectedAgentId', $this->agent->id)
            ->call('createConversation');

        $this->assertDatabaseCount('ai_conversations', 1);
        $this->assertDatabaseHas('ai_conversations', [
            'user_id' => $this->user->id,
            'ai_agent_id' => $this->agent->id,
        ]);
    }

    public function test_switch_conversation(): void
    {
        $convo = AiConversation::factory()->create([
            'user_id' => $this->user->id,
            'ai_agent_id' => $this->agent->id,
        ]);

        Livewire::actingAs($this->user)
            ->test(AiAssistantPanel::class)
            ->call('switchConversation', $convo->id)
            ->assertSet('activeConversationId', $convo->id)
            ->assertSet('selectedAgentId', $this->agent->id);
    }

    public function test_switch_conversation_rejects_other_users_conversation(): void
    {
        $otherUser = User::factory()->create();
        $convo = AiConversation::factory()->create([
            'user_id' => $otherUser->id,
            'ai_agent_id' => $this->agent->id,
        ]);

        Livewire::actingAs($this->user)
            ->test(AiAssistantPanel::class)
            ->call('switchConversation', $convo->id)
            ->assertSet('activeConversationId', null);
    }

    public function test_delete_conversation(): void
    {
        $convo = AiConversation::factory()->create([
            'user_id' => $this->user->id,
            'ai_agent_id' => $this->agent->id,
        ]);

        Livewire::actingAs($this->user)
            ->test(AiAssistantPanel::class)
            ->set('activeConversationId', $convo->id)
            ->call('deleteConversation', $convo->id)
            ->assertSet('activeConversationId', null);

        $this->assertSoftDeleted('ai_conversations', ['id' => $convo->id]);
    }

    public function test_delete_conversation_rejects_other_users(): void
    {
        $otherUser = User::factory()->create();
        $convo = AiConversation::factory()->create([
            'user_id' => $otherUser->id,
            'ai_agent_id' => $this->agent->id,
        ]);

        Livewire::actingAs($this->user)
            ->test(AiAssistantPanel::class)
            ->call('deleteConversation', $convo->id);

        $this->assertNotSoftDeleted('ai_conversations', ['id' => $convo->id]);
    }

    public function test_load_messages_returns_conversation_messages(): void
    {
        $convo = AiConversation::factory()->create([
            'user_id' => $this->user->id,
            'ai_agent_id' => $this->agent->id,
        ]);

        AiMessage::factory()->create([
            'ai_conversation_id' => $convo->id,
            'role' => 'user',
            'content' => 'Hello',
        ]);

        AiMessage::factory()->create([
            'ai_conversation_id' => $convo->id,
            'role' => 'assistant',
            'content' => 'Hi there!',
        ]);

        $component = Livewire::actingAs($this->user)
            ->test(AiAssistantPanel::class)
            ->set('activeConversationId', $convo->id);

        $messages = $component->instance()->loadMessages();
        $this->assertCount(2, $messages);
        $this->assertEquals('user', $messages[0]['role']);
        $this->assertEquals('Hello', $messages[0]['content']);
        $this->assertEquals('assistant', $messages[1]['role']);
    }

    public function test_load_messages_returns_empty_when_no_conversation(): void
    {
        $component = Livewire::actingAs($this->user)
            ->test(AiAssistantPanel::class);

        $messages = $component->instance()->loadMessages();
        $this->assertEmpty($messages);
    }

    public function test_send_message_returns_error_when_empty(): void
    {
        $component = Livewire::actingAs($this->user)
            ->test(AiAssistantPanel::class);

        $result = $component->instance()->sendMessage('   ');
        $this->assertArrayHasKey('error', $result);
    }
}
