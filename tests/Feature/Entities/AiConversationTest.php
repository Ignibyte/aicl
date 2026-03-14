<?php

namespace Aicl\Tests\Feature\Entities;

use Aicl\Models\AiAgent;
use Aicl\Models\AiConversation;
use Aicl\States\AiConversation\Active;
use Aicl\States\AiConversation\Archived;
use Aicl\States\AiConversation\Summarized;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AiConversationTest extends TestCase
{
    use RefreshDatabase;

    // Observer registered via AiclServiceProvider::boot()

    // ─── Model & Factory ────────────────────────────────────────

    public function test_can_create_conversation(): void
    {
        $user = User::factory()->create();
        $agent = AiAgent::factory()->create();

        $conversation = AiConversation::factory()->create([
            'title' => 'Test Conversation',
            'user_id' => $user->id,
            'ai_agent_id' => $agent->id,
        ]);

        $this->assertDatabaseHas('ai_conversations', [
            'id' => $conversation->id,
            'title' => 'Test Conversation',
            'user_id' => $user->id,
            'ai_agent_id' => $agent->id,
        ]);
    }

    public function test_factory_default_state_is_active(): void
    {
        $conversation = AiConversation::factory()->create();

        $this->assertInstanceOf(Active::class, $conversation->state);
    }

    public function test_factory_summarized_state(): void
    {
        $conversation = AiConversation::factory()->summarized()->create();

        $this->assertInstanceOf(Summarized::class, $conversation->state);
        $this->assertNotNull($conversation->summary);
    }

    public function test_factory_archived_state(): void
    {
        $conversation = AiConversation::factory()->archived()->create();

        $this->assertInstanceOf(Archived::class, $conversation->state);
    }

    public function test_factory_pinned(): void
    {
        $conversation = AiConversation::factory()->pinned()->create();

        $this->assertTrue($conversation->is_pinned);
    }

    public function test_factory_for_agent(): void
    {
        $agent = AiAgent::factory()->create();
        $conversation = AiConversation::factory()->forAgent($agent)->create();

        $this->assertEquals($agent->id, $conversation->ai_agent_id);
    }

    // ─── Casts ──────────────────────────────────────────────────

    public function test_boolean_cast(): void
    {
        $conversation = AiConversation::factory()->create(['is_pinned' => true]);

        $this->assertIsBool($conversation->is_pinned);
        $this->assertTrue($conversation->is_pinned);
    }

    public function test_integer_casts(): void
    {
        $conversation = AiConversation::factory()->create([
            'message_count' => 42,
            'token_count' => 5000,
        ]);

        $this->assertIsInt($conversation->message_count);
        $this->assertIsInt($conversation->token_count);
        $this->assertEquals(42, $conversation->message_count);
        $this->assertEquals(5000, $conversation->token_count);
    }

    public function test_datetime_cast(): void
    {
        $conversation = AiConversation::factory()->create([
            'last_message_at' => '2026-03-13 12:00:00',
        ]);

        $this->assertInstanceOf(Carbon::class, $conversation->last_message_at);
    }

    // ─── Relationships ──────────────────────────────────────────

    public function test_belongs_to_user(): void
    {
        $user = User::factory()->create();
        $conversation = AiConversation::factory()->create(['user_id' => $user->id]);

        $this->assertInstanceOf(User::class, $conversation->user);
        $this->assertEquals($user->id, $conversation->user->id);
    }

    public function test_belongs_to_agent(): void
    {
        $agent = AiAgent::factory()->create();
        $conversation = AiConversation::factory()->create(['ai_agent_id' => $agent->id]);

        $this->assertInstanceOf(AiAgent::class, $conversation->agent);
        $this->assertEquals($agent->id, $conversation->agent->id);
    }

    // ─── Observer ───────────────────────────────────────────────

    public function test_title_auto_generated_when_empty(): void
    {
        $conversation = AiConversation::factory()->create([
            'title' => null,
        ]);

        $this->assertNotNull($conversation->title);
        $this->assertStringStartsWith('New Conversation', $conversation->title);
    }

    public function test_title_preserved_when_provided(): void
    {
        $conversation = AiConversation::factory()->create([
            'title' => 'My Custom Title',
        ]);

        $this->assertEquals('My Custom Title', $conversation->title);
    }

    // ─── State Machine ──────────────────────────────────────────

    public function test_active_can_transition_to_summarized(): void
    {
        $conversation = AiConversation::factory()->create();

        $conversation->state->transitionTo(Summarized::class);

        $this->assertInstanceOf(Summarized::class, $conversation->refresh()->state);
    }

    public function test_active_can_transition_to_archived(): void
    {
        $conversation = AiConversation::factory()->create();

        $conversation->state->transitionTo(Archived::class);

        $this->assertInstanceOf(Archived::class, $conversation->refresh()->state);
    }

    public function test_summarized_can_transition_to_archived(): void
    {
        $conversation = AiConversation::factory()->summarized()->create();

        $conversation->state->transitionTo(Archived::class);

        $this->assertInstanceOf(Archived::class, $conversation->refresh()->state);
    }

    public function test_archived_can_transition_to_active(): void
    {
        $conversation = AiConversation::factory()->archived()->create();

        $conversation->state->transitionTo(Active::class);

        $this->assertInstanceOf(Active::class, $conversation->refresh()->state);
    }

    // ─── Scopes ─────────────────────────────────────────────────

    public function test_for_user_scope(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        AiConversation::factory()->create(['user_id' => $user1->id]);
        AiConversation::factory()->create(['user_id' => $user1->id]);
        AiConversation::factory()->create(['user_id' => $user2->id]);

        $results = AiConversation::query()->forUser($user1)->get();

        $this->assertCount(2, $results);
    }

    public function test_active_scope(): void
    {
        AiConversation::factory()->create(); // active by default
        AiConversation::factory()->archived()->create();
        AiConversation::factory()->summarized()->create();

        $results = AiConversation::query()->active()->get();

        $this->assertCount(1, $results);
    }

    public function test_recent_scope_orders_by_last_message(): void
    {
        $older = AiConversation::factory()->create(['last_message_at' => now()->subDays(2)]);
        $newer = AiConversation::factory()->create(['last_message_at' => now()->subDay()]);

        $results = AiConversation::query()->recent()->get();

        $this->assertEquals($newer->id, $results->first()->id);
    }

    public function test_pinned_scope(): void
    {
        AiConversation::factory()->pinned()->create();
        AiConversation::factory()->create(['is_pinned' => false]);

        $results = AiConversation::query()->pinned()->get();

        $this->assertCount(1, $results);
        $this->assertTrue($results->first()->is_pinned);
    }

    // ─── Accessors ──────────────────────────────────────────────

    public function test_display_title_returns_title(): void
    {
        $conversation = AiConversation::factory()->create(['title' => 'My Chat']);

        $this->assertEquals('My Chat', $conversation->display_title);
    }

    public function test_display_title_returns_default_when_null(): void
    {
        $conversation = AiConversation::factory()->make(['title' => null]);
        // Use make to avoid observer setting title
        $conversation->title = null;

        $this->assertEquals('New Conversation', $conversation->display_title);
    }

    public function test_is_compactable_when_above_threshold(): void
    {
        config(['aicl.ai.assistant.compaction_threshold' => 50]);

        $conversation = AiConversation::factory()->create([
            'message_count' => 51,
            'summary' => null,
        ]);

        $this->assertTrue($conversation->is_compactable);
    }

    public function test_is_not_compactable_when_below_threshold(): void
    {
        config(['aicl.ai.assistant.compaction_threshold' => 50]);

        $conversation = AiConversation::factory()->create([
            'message_count' => 30,
            'summary' => null,
        ]);

        $this->assertFalse($conversation->is_compactable);
    }

    public function test_is_not_compactable_when_already_summarized(): void
    {
        config(['aicl.ai.assistant.compaction_threshold' => 50]);

        $conversation = AiConversation::factory()->create([
            'message_count' => 100,
            'summary' => 'Already summarized content.',
        ]);

        $this->assertFalse($conversation->is_compactable);
    }

    // ─── Soft Deletes ───────────────────────────────────────────

    public function test_soft_delete(): void
    {
        $conversation = AiConversation::factory()->create();

        $conversation->delete();

        $this->assertSoftDeleted('ai_conversations', ['id' => $conversation->id]);
    }

    // ─── Searchable ─────────────────────────────────────────────

    public function test_search_scope_finds_by_title(): void
    {
        AiConversation::factory()->create(['title' => 'Debugging Redis Issues']);
        AiConversation::factory()->create(['title' => 'General Chat']);

        $results = AiConversation::query()->search('Redis')->get();

        $this->assertCount(1, $results);
        $this->assertEquals('Debugging Redis Issues', $results->first()->title);
    }
}
