<?php

namespace Aicl\Tests\Feature\Api;

use Aicl\Events\EntityCreated;
use Aicl\Events\EntityDeleted;
use Aicl\Events\EntityUpdated;
use Aicl\Models\AiAgent;
use Aicl\Models\AiConversation;
use Aicl\Models\AiMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Passport\Passport;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AiMessageControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $viewer;

    private User $otherUser;

    private AiAgent $agent;

    private AiConversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPermissionsAndRoles();

        Event::fake([EntityCreated::class, EntityUpdated::class, EntityDeleted::class]);

        $this->admin = User::factory()->create();
        $this->admin->assignRole(Role::findByName('admin', 'api'));

        $this->viewer = User::factory()->create();
        $this->viewer->assignRole(Role::findByName('viewer', 'api'));

        $this->otherUser = User::factory()->create();
        $this->otherUser->assignRole(Role::findByName('admin', 'api'));

        $this->agent = AiAgent::factory()->active()->create();

        $this->conversation = AiConversation::factory()->create([
            'user_id' => $this->admin->id,
            'ai_agent_id' => $this->agent->id,
        ]);
    }

    /**
     * Create AiConversation permissions on the api guard and assign to roles.
     * Message endpoints authorize via AiConversationPolicy (view/update).
     */
    private function seedPermissionsAndRoles(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $actions = ['ViewAny', 'View', 'Create', 'Update', 'Delete'];

        foreach ($actions as $action) {
            Permission::firstOrCreate([
                'name' => "{$action}:AiConversation",
                'guard_name' => 'api',
            ]);
        }

        $allPerms = Permission::where('guard_name', 'api')->get();

        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'api']);
        $admin->syncPermissions($allPerms);

        $viewer = Role::firstOrCreate(['name' => 'viewer', 'guard_name' => 'api']);
        $viewer->syncPermissions(
            $allPerms->filter(fn (Permission $p) => str_starts_with($p->name, 'ViewAny:') || str_starts_with($p->name, 'View:'))
        );

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    // ─── Authentication ─────────────────────────────────────────

    public function test_index_requires_authentication(): void
    {
        $response = $this->getJson("/api/v1/ai-conversations/{$this->conversation->id}/messages");

        $response->assertUnauthorized();
    }

    public function test_store_requires_authentication(): void
    {
        $response = $this->postJson("/api/v1/ai-conversations/{$this->conversation->id}/messages", []);

        $response->assertUnauthorized();
    }

    public function test_destroy_requires_authentication(): void
    {
        $message = AiMessage::factory()->create([
            'ai_conversation_id' => $this->conversation->id,
        ]);

        $response = $this->deleteJson(
            "/api/v1/ai-conversations/{$this->conversation->id}/messages/{$message->id}"
        );

        $response->assertUnauthorized();
    }

    // ─── Authorization ──────────────────────────────────────────

    public function test_unprivileged_user_cannot_list_messages_of_other_users_conversation(): void
    {
        $unprivileged = User::factory()->create();
        Passport::actingAs($unprivileged, ['*']);

        $response = $this->getJson("/api/v1/ai-conversations/{$this->conversation->id}/messages");

        $response->assertForbidden();
    }

    public function test_viewer_can_list_messages_of_any_conversation(): void
    {
        Passport::actingAs($this->viewer, ['*']);

        // Viewer has ViewAny:AiConversation — policy grants view access
        $response = $this->getJson("/api/v1/ai-conversations/{$this->conversation->id}/messages");

        $response->assertOk();
    }

    public function test_viewer_can_list_messages_of_own_conversation(): void
    {
        Passport::actingAs($this->viewer, ['*']);

        $conversation = AiConversation::factory()->create([
            'user_id' => $this->viewer->id,
            'ai_agent_id' => $this->agent->id,
        ]);

        $response = $this->getJson("/api/v1/ai-conversations/{$conversation->id}/messages");

        $response->assertOk();
    }

    public function test_viewer_cannot_store_message_in_other_users_conversation(): void
    {
        Passport::actingAs($this->viewer, ['*']);

        $response = $this->postJson("/api/v1/ai-conversations/{$this->conversation->id}/messages", [
            'role' => 'user',
            'content' => 'Hello',
        ]);

        $response->assertForbidden();
    }

    public function test_viewer_cannot_delete_message_from_other_users_conversation(): void
    {
        Passport::actingAs($this->viewer, ['*']);

        $message = AiMessage::factory()->create([
            'ai_conversation_id' => $this->conversation->id,
        ]);

        $response = $this->deleteJson(
            "/api/v1/ai-conversations/{$this->conversation->id}/messages/{$message->id}"
        );

        $response->assertForbidden();
    }

    public function test_admin_can_list_messages_of_any_conversation(): void
    {
        Passport::actingAs($this->otherUser, ['*']);

        AiMessage::factory()->count(2)->create([
            'ai_conversation_id' => $this->conversation->id,
        ]);

        $response = $this->getJson("/api/v1/ai-conversations/{$this->conversation->id}/messages");

        $response->assertOk();
    }

    // ─── Index ──────────────────────────────────────────────────

    public function test_index_returns_paginated_messages(): void
    {
        Passport::actingAs($this->admin, ['*']);

        AiMessage::factory()->count(3)->create([
            'ai_conversation_id' => $this->conversation->id,
        ]);

        $response = $this->getJson("/api/v1/ai-conversations/{$this->conversation->id}/messages");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'ai_conversation_id',
                        'role',
                        'content',
                        'token_count',
                        'metadata',
                        'created_at',
                        'updated_at',
                    ],
                ],
                'links',
                'meta',
            ]);

        $this->assertCount(3, $response->json('data'));
    }

    public function test_index_returns_messages_ordered_by_created_at(): void
    {
        Passport::actingAs($this->admin, ['*']);

        AiMessage::factory()->fromUser()->create([
            'ai_conversation_id' => $this->conversation->id,
            'content' => 'First message',
            'created_at' => now()->subMinutes(2),
        ]);
        AiMessage::factory()->fromAssistant()->create([
            'ai_conversation_id' => $this->conversation->id,
            'content' => 'Second message',
            'created_at' => now()->subMinute(),
        ]);
        AiMessage::factory()->fromUser()->create([
            'ai_conversation_id' => $this->conversation->id,
            'content' => 'Third message',
            'created_at' => now(),
        ]);

        $response = $this->getJson("/api/v1/ai-conversations/{$this->conversation->id}/messages");

        $response->assertOk();

        $contents = collect($response->json('data'))->pluck('content')->toArray();
        $this->assertEquals(['First message', 'Second message', 'Third message'], $contents);
    }

    public function test_index_only_returns_messages_for_specified_conversation(): void
    {
        Passport::actingAs($this->admin, ['*']);

        AiMessage::factory()->count(2)->create([
            'ai_conversation_id' => $this->conversation->id,
        ]);

        $otherConversation = AiConversation::factory()->create([
            'user_id' => $this->admin->id,
        ]);
        AiMessage::factory()->count(3)->create([
            'ai_conversation_id' => $otherConversation->id,
        ]);

        $response = $this->getJson("/api/v1/ai-conversations/{$this->conversation->id}/messages");

        $response->assertOk();

        $this->assertCount(2, $response->json('data'));
    }

    public function test_index_returns_empty_for_conversation_with_no_messages(): void
    {
        Passport::actingAs($this->admin, ['*']);

        $response = $this->getJson("/api/v1/ai-conversations/{$this->conversation->id}/messages");

        $response->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_index_returns_404_for_nonexistent_conversation(): void
    {
        Passport::actingAs($this->admin, ['*']);

        $response = $this->getJson('/api/v1/ai-conversations/00000000-0000-0000-0000-000000000000/messages');

        $response->assertNotFound();
    }

    // ─── Store ──────────────────────────────────────────────────

    public function test_store_creates_user_message(): void
    {
        Passport::actingAs($this->admin, ['*']);

        $response = $this->postJson("/api/v1/ai-conversations/{$this->conversation->id}/messages", [
            'role' => 'user',
            'content' => 'Hello, how are you?',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.role', 'user')
            ->assertJsonPath('data.content', 'Hello, how are you?')
            ->assertJsonPath('data.ai_conversation_id', $this->conversation->id);

        $this->assertDatabaseHas('ai_messages', [
            'ai_conversation_id' => $this->conversation->id,
            'role' => 'user',
            'content' => 'Hello, how are you?',
        ]);
    }

    public function test_store_creates_message_with_token_count(): void
    {
        Passport::actingAs($this->admin, ['*']);

        $response = $this->postJson("/api/v1/ai-conversations/{$this->conversation->id}/messages", [
            'role' => 'user',
            'content' => 'Count my tokens.',
            'token_count' => 42,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.token_count', 42);
    }

    public function test_store_validates_required_role(): void
    {
        Passport::actingAs($this->admin, ['*']);

        $response = $this->postJson("/api/v1/ai-conversations/{$this->conversation->id}/messages", [
            'content' => 'Hello',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['role']);
    }

    public function test_store_validates_role_must_be_user(): void
    {
        Passport::actingAs($this->admin, ['*']);

        $response = $this->postJson("/api/v1/ai-conversations/{$this->conversation->id}/messages", [
            'role' => 'assistant',
            'content' => 'I should not be allowed.',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['role']);
    }

    public function test_store_validates_role_rejects_system(): void
    {
        Passport::actingAs($this->admin, ['*']);

        $response = $this->postJson("/api/v1/ai-conversations/{$this->conversation->id}/messages", [
            'role' => 'system',
            'content' => 'System message.',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['role']);
    }

    public function test_store_validates_required_content(): void
    {
        Passport::actingAs($this->admin, ['*']);

        $response = $this->postJson("/api/v1/ai-conversations/{$this->conversation->id}/messages", [
            'role' => 'user',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['content']);
    }

    public function test_store_validates_content_max_length(): void
    {
        Passport::actingAs($this->admin, ['*']);

        $response = $this->postJson("/api/v1/ai-conversations/{$this->conversation->id}/messages", [
            'role' => 'user',
            'content' => str_repeat('a', 2001),
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['content']);
    }

    public function test_store_validates_token_count_min(): void
    {
        Passport::actingAs($this->admin, ['*']);

        $response = $this->postJson("/api/v1/ai-conversations/{$this->conversation->id}/messages", [
            'role' => 'user',
            'content' => 'Hello',
            'token_count' => -1,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['token_count']);
    }

    public function test_store_returns_404_for_nonexistent_conversation(): void
    {
        Passport::actingAs($this->admin, ['*']);

        $response = $this->postJson('/api/v1/ai-conversations/00000000-0000-0000-0000-000000000000/messages', [
            'role' => 'user',
            'content' => 'Hello',
        ]);

        $response->assertNotFound();
    }

    public function test_owner_can_store_message_in_own_conversation(): void
    {
        Passport::actingAs($this->viewer, ['*']);

        $conversation = AiConversation::factory()->create([
            'user_id' => $this->viewer->id,
            'ai_agent_id' => $this->agent->id,
        ]);

        $response = $this->postJson("/api/v1/ai-conversations/{$conversation->id}/messages", [
            'role' => 'user',
            'content' => 'Hello from owner',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.content', 'Hello from owner');
    }

    // ─── Destroy ────────────────────────────────────────────────

    public function test_destroy_deletes_message(): void
    {
        Passport::actingAs($this->admin, ['*']);

        $message = AiMessage::factory()->create([
            'ai_conversation_id' => $this->conversation->id,
        ]);

        $response = $this->deleteJson(
            "/api/v1/ai-conversations/{$this->conversation->id}/messages/{$message->id}"
        );

        $response->assertNoContent();

        $this->assertDatabaseMissing('ai_messages', ['id' => $message->id]);
    }

    public function test_destroy_returns_404_when_message_belongs_to_different_conversation(): void
    {
        Passport::actingAs($this->admin, ['*']);

        $otherConversation = AiConversation::factory()->create([
            'user_id' => $this->admin->id,
        ]);
        $message = AiMessage::factory()->create([
            'ai_conversation_id' => $otherConversation->id,
        ]);

        $response = $this->deleteJson(
            "/api/v1/ai-conversations/{$this->conversation->id}/messages/{$message->id}"
        );

        $response->assertNotFound();

        $this->assertDatabaseHas('ai_messages', ['id' => $message->id]);
    }

    public function test_destroy_returns_404_for_nonexistent_conversation(): void
    {
        Passport::actingAs($this->admin, ['*']);

        $message = AiMessage::factory()->create([
            'ai_conversation_id' => $this->conversation->id,
        ]);

        $response = $this->deleteJson(
            "/api/v1/ai-conversations/00000000-0000-0000-0000-000000000000/messages/{$message->id}"
        );

        $response->assertNotFound();
    }

    public function test_destroy_returns_404_for_nonexistent_message(): void
    {
        Passport::actingAs($this->admin, ['*']);

        $response = $this->deleteJson(
            "/api/v1/ai-conversations/{$this->conversation->id}/messages/00000000-0000-0000-0000-000000000000"
        );

        $response->assertNotFound();
    }

    public function test_owner_can_delete_message_from_own_conversation(): void
    {
        Passport::actingAs($this->viewer, ['*']);

        $conversation = AiConversation::factory()->create([
            'user_id' => $this->viewer->id,
            'ai_agent_id' => $this->agent->id,
        ]);

        $message = AiMessage::factory()->create([
            'ai_conversation_id' => $conversation->id,
        ]);

        $response = $this->deleteJson(
            "/api/v1/ai-conversations/{$conversation->id}/messages/{$message->id}"
        );

        $response->assertNoContent();
    }

    // ─── Resource Structure ─────────────────────────────────────

    public function test_resource_includes_role_as_string_value(): void
    {
        Passport::actingAs($this->admin, ['*']);

        AiMessage::factory()->fromUser()->create([
            'ai_conversation_id' => $this->conversation->id,
        ]);
        AiMessage::factory()->fromAssistant()->create([
            'ai_conversation_id' => $this->conversation->id,
        ]);

        $response = $this->getJson("/api/v1/ai-conversations/{$this->conversation->id}/messages");

        $response->assertOk();

        $roles = collect($response->json('data'))->pluck('role')->toArray();
        $this->assertContains('user', $roles);
        $this->assertContains('assistant', $roles);
    }

    public function test_resource_includes_metadata_when_present(): void
    {
        Passport::actingAs($this->admin, ['*']);

        AiMessage::factory()->withMetadata()->create([
            'ai_conversation_id' => $this->conversation->id,
        ]);

        $response = $this->getJson("/api/v1/ai-conversations/{$this->conversation->id}/messages");

        $response->assertOk();

        $metadata = $response->json('data.0.metadata');
        $this->assertNotNull($metadata);
        $this->assertArrayHasKey('model', $metadata);
    }
}
