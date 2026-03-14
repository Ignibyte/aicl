<?php

namespace Aicl\Tests\Feature\Entities;

use Aicl\Enums\AiMessageRole;
use Aicl\Models\AiConversation;
use Aicl\Models\AiMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiMessageTest extends TestCase
{
    use RefreshDatabase;

    // Observers registered via AiclServiceProvider::boot()

    // ─── Model & Factory ────────────────────────────────────────

    public function test_can_create_message(): void
    {
        $conversation = AiConversation::factory()->create();

        $message = AiMessage::factory()->create([
            'ai_conversation_id' => $conversation->id,
            'role' => AiMessageRole::User,
            'content' => 'Hello, how are you?',
        ]);

        $this->assertDatabaseHas('ai_messages', [
            'id' => $message->id,
            'ai_conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => 'Hello, how are you?',
        ]);
    }

    public function test_factory_from_user(): void
    {
        $message = AiMessage::factory()->fromUser()->create();

        $this->assertEquals(AiMessageRole::User, $message->role);
    }

    public function test_factory_from_assistant(): void
    {
        $message = AiMessage::factory()->fromAssistant()->create();

        $this->assertEquals(AiMessageRole::Assistant, $message->role);
    }

    public function test_factory_system(): void
    {
        $message = AiMessage::factory()->system()->create();

        $this->assertEquals(AiMessageRole::System, $message->role);
    }

    public function test_factory_with_metadata(): void
    {
        $message = AiMessage::factory()->withMetadata()->create();

        $this->assertIsArray($message->metadata);
        $this->assertArrayHasKey('model', $message->metadata);
    }

    // ─── Casts ──────────────────────────────────────────────────

    public function test_role_is_cast_to_enum(): void
    {
        $message = AiMessage::factory()->fromUser()->create();

        $this->assertInstanceOf(AiMessageRole::class, $message->role);
    }

    public function test_metadata_is_cast_to_array(): void
    {
        $message = AiMessage::factory()->withMetadata()->create();

        $this->assertIsArray($message->metadata);
    }

    public function test_token_count_cast(): void
    {
        $message = AiMessage::factory()->create(['token_count' => 150]);

        $this->assertIsInt($message->token_count);
        $this->assertEquals(150, $message->token_count);
    }

    // ─── Relationships ──────────────────────────────────────────

    public function test_belongs_to_conversation(): void
    {
        $conversation = AiConversation::factory()->create();
        $message = AiMessage::factory()->create(['ai_conversation_id' => $conversation->id]);

        $this->assertInstanceOf(AiConversation::class, $message->conversation);
        $this->assertEquals($conversation->id, $message->conversation->id);
    }

    // ─── Helpers ────────────────────────────────────────────────

    public function test_is_from_user(): void
    {
        $message = AiMessage::factory()->fromUser()->create();

        $this->assertTrue($message->isFromUser());
        $this->assertFalse($message->isFromAssistant());
        $this->assertFalse($message->isSystem());
    }

    public function test_is_from_assistant(): void
    {
        $message = AiMessage::factory()->fromAssistant()->create();

        $this->assertTrue($message->isFromAssistant());
        $this->assertFalse($message->isFromUser());
    }

    public function test_is_system(): void
    {
        $message = AiMessage::factory()->system()->create();

        $this->assertTrue($message->isSystem());
        $this->assertFalse($message->isFromUser());
    }

    // ─── Observer: Counter Updates ──────────────────────────────

    public function test_creating_message_increments_conversation_message_count(): void
    {
        $conversation = AiConversation::factory()->create([
            'message_count' => 0,
            'token_count' => 0,
        ]);

        AiMessage::factory()->create([
            'ai_conversation_id' => $conversation->id,
            'token_count' => 100,
        ]);

        $conversation->refresh();

        $this->assertEquals(1, $conversation->message_count);
        $this->assertEquals(100, $conversation->token_count);
    }

    public function test_creating_message_updates_last_message_at(): void
    {
        $conversation = AiConversation::factory()->create([
            'last_message_at' => null,
        ]);

        AiMessage::factory()->create([
            'ai_conversation_id' => $conversation->id,
        ]);

        $conversation->refresh();

        $this->assertNotNull($conversation->last_message_at);
    }

    public function test_deleting_message_decrements_conversation_counters(): void
    {
        $conversation = AiConversation::factory()->create([
            'message_count' => 5,
            'token_count' => 1000,
        ]);

        $message = AiMessage::factory()->create([
            'ai_conversation_id' => $conversation->id,
            'token_count' => 200,
        ]);

        // After create, counts are 6 and 1200
        $conversation->refresh();
        $this->assertEquals(6, $conversation->message_count);

        $message->delete();
        $conversation->refresh();

        $this->assertEquals(5, $conversation->message_count);
        $this->assertEquals(1000, $conversation->token_count);
    }

    // ─── Enum ───────────────────────────────────────────────────

    public function test_ai_message_role_labels(): void
    {
        $this->assertEquals('User', AiMessageRole::User->getLabel());
        $this->assertEquals('Assistant', AiMessageRole::Assistant->getLabel());
        $this->assertEquals('System', AiMessageRole::System->getLabel());
    }

    public function test_ai_message_role_colors(): void
    {
        $this->assertEquals('primary', AiMessageRole::User->getColor());
        $this->assertEquals('success', AiMessageRole::Assistant->getColor());
        $this->assertEquals('gray', AiMessageRole::System->getColor());
    }
}
