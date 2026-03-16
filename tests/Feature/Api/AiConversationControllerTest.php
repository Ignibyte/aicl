<?php

namespace Aicl\Tests\Feature\Api;

use Aicl\Events\EntityCreated;
use Aicl\Events\EntityDeleted;
use Aicl\Events\EntityUpdated;
use Aicl\Models\AiAgent;
use Aicl\Models\AiConversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Passport\Passport;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AiConversationControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $viewer;

    private User $otherUser;

    private AiAgent $agent;

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
    }

    /**
     * Create AiConversation permissions on the api guard and assign to roles.
     * Shield's `shield:generate` creates these in production; tests must
     * seed them manually since RefreshDatabase wipes the permissions table.
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
        $response = $this->getJson('/api/v1/ai-conversations');

        $response->assertUnauthorized();
    }

    public function test_store_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/ai-conversations', []);

        $response->assertUnauthorized();
    }

    public function test_show_requires_authentication(): void
    {
        $conversation = AiConversation::factory()->create();

        $response = $this->getJson("/api/v1/ai-conversations/{$conversation->id}");

        $response->assertUnauthorized();
    }

    public function test_update_requires_authentication(): void
    {
        $conversation = AiConversation::factory()->create();

        $response = $this->putJson("/api/v1/ai-conversations/{$conversation->id}", []);

        $response->assertUnauthorized();
    }

    public function test_destroy_requires_authentication(): void
    {
        $conversation = AiConversation::factory()->create();

        $response = $this->deleteJson("/api/v1/ai-conversations/{$conversation->id}");

        $response->assertUnauthorized();
    }

    // ─── Authorization ──────────────────────────────────────────

    public function test_viewer_can_list_conversations(): void
    {
        Passport::actingAs($this->viewer, ['*']);

        AiConversation::factory()->create(['user_id' => $this->viewer->id]);

        $response = $this->getJson('/api/v1/ai-conversations');

        $response->assertOk();
    }

    public function test_viewer_can_view_own_conversation(): void
    {
        Passport::actingAs($this->viewer, ['*']);

        $conversation = AiConversation::factory()->create([
            'user_id' => $this->viewer->id,
        ]);

        $response = $this->getJson("/api/v1/ai-conversations/{$conversation->id}");

        $response->assertOk();
    }

    public function test_viewer_with_viewany_permission_can_view_any_conversation(): void
    {
        Passport::actingAs($this->viewer, ['*']);

        $conversation = AiConversation::factory()->create([
            'user_id' => $this->admin->id,
        ]);

        // Viewer role has ViewAny:AiConversation — policy grants access
        $response = $this->getJson("/api/v1/ai-conversations/{$conversation->id}");

        $response->assertOk();
    }

    public function test_unprivileged_user_cannot_view_other_users_conversation(): void
    {
        $unprivileged = User::factory()->create();
        // No role assigned — no permissions at all
        Passport::actingAs($unprivileged, ['*']);

        $conversation = AiConversation::factory()->create([
            'user_id' => $this->admin->id,
        ]);

        $response = $this->getJson("/api/v1/ai-conversations/{$conversation->id}");

        $response->assertForbidden();
    }

    public function test_viewer_cannot_create_conversation(): void
    {
        Passport::actingAs($this->viewer, ['*']);

        $response = $this->postJson('/api/v1/ai-conversations', [
            'ai_agent_id' => $this->agent->id,
        ]);

        $response->assertForbidden();
    }

    public function test_viewer_cannot_update_other_users_conversation(): void
    {
        Passport::actingAs($this->viewer, ['*']);

        $conversation = AiConversation::factory()->create([
            'user_id' => $this->admin->id,
        ]);

        $response = $this->putJson("/api/v1/ai-conversations/{$conversation->id}", [
            'title' => 'Updated Title',
        ]);

        $response->assertForbidden();
    }

    public function test_viewer_cannot_delete_other_users_conversation(): void
    {
        Passport::actingAs($this->viewer, ['*']);

        $conversation = AiConversation::factory()->create([
            'user_id' => $this->admin->id,
        ]);

        $response = $this->deleteJson("/api/v1/ai-conversations/{$conversation->id}");

        $response->assertForbidden();
    }

    public function test_admin_can_view_other_users_conversation(): void
    {
        Passport::actingAs($this->admin, ['*']);

        $conversation = AiConversation::factory()->create([
            'user_id' => $this->otherUser->id,
        ]);

        $response = $this->getJson("/api/v1/ai-conversations/{$conversation->id}");

        $response->assertOk();
    }

    public function test_owner_can_update_own_conversation(): void
    {
        Passport::actingAs($this->viewer, ['*']);

        $conversation = AiConversation::factory()->create([
            'user_id' => $this->viewer->id,
        ]);

        $response = $this->putJson("/api/v1/ai-conversations/{$conversation->id}", [
            'title' => 'My Updated Title',
        ]);

        $response->assertOk();
    }

    public function test_owner_can_delete_own_conversation(): void
    {
        Passport::actingAs($this->viewer, ['*']);

        $conversation = AiConversation::factory()->create([
            'user_id' => $this->viewer->id,
        ]);

        $response = $this->deleteJson("/api/v1/ai-conversations/{$conversation->id}");

        $response->assertNoContent();
    }

    // ─── Index ──────────────────────────────────────────────────

    public function test_index_returns_paginated_conversations(): void
    {
        Passport::actingAs($this->admin, ['*']);

        AiConversation::factory()->count(3)->create([
            'user_id' => $this->admin->id,
            'ai_agent_id' => $this->agent->id,
        ]);

        $response = $this->getJson('/api/v1/ai-conversations');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'title',
                        'display_title',
                        'user_id',
                        'ai_agent_id',
                        'message_count',
                        'token_count',
                        'is_pinned',
                        'state',
                        'created_at',
                        'updated_at',
                    ],
                ],
                'links',
                'meta',
            ]);

        $this->assertCount(3, $response->json('data'));
    }

    public function test_index_only_returns_current_users_conversations(): void
    {
        Passport::actingAs($this->admin, ['*']);

        AiConversation::factory()->count(2)->create([
            'user_id' => $this->admin->id,
        ]);
        AiConversation::factory()->count(3)->create([
            'user_id' => $this->otherUser->id,
        ]);

        $response = $this->getJson('/api/v1/ai-conversations');

        $response->assertOk();

        $this->assertCount(2, $response->json('data'));
    }

    public function test_index_returns_conversations_ordered_by_recent_message(): void
    {
        Passport::actingAs($this->admin, ['*']);

        AiConversation::factory()->create([
            'user_id' => $this->admin->id,
            'title' => 'Older',
            'last_message_at' => now()->subDays(2),
        ]);
        AiConversation::factory()->create([
            'user_id' => $this->admin->id,
            'title' => 'Newest',
            'last_message_at' => now(),
        ]);
        AiConversation::factory()->create([
            'user_id' => $this->admin->id,
            'title' => 'Middle',
            'last_message_at' => now()->subDay(),
        ]);

        $response = $this->getJson('/api/v1/ai-conversations');

        $response->assertOk();

        $titles = collect($response->json('data'))->pluck('title')->toArray();
        $this->assertEquals(['Newest', 'Middle', 'Older'], $titles);
    }

    public function test_index_includes_loaded_user_and_agent(): void
    {
        Passport::actingAs($this->admin, ['*']);

        AiConversation::factory()->create([
            'user_id' => $this->admin->id,
            'ai_agent_id' => $this->agent->id,
        ]);

        $response = $this->getJson('/api/v1/ai-conversations');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'user' => ['id', 'name'],
                        'agent' => ['id', 'name', 'icon', 'color'],
                    ],
                ],
            ]);
    }

    // ─── Store ──────────────────────────────────────────────────

    public function test_store_creates_conversation(): void
    {
        Passport::actingAs($this->admin, ['*']);

        $response = $this->postJson('/api/v1/ai-conversations', [
            'title' => 'My Conversation',
            'ai_agent_id' => $this->agent->id,
            'context_page' => '/admin/dashboard',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.title', 'My Conversation')
            ->assertJsonPath('data.ai_agent_id', $this->agent->id)
            ->assertJsonPath('data.user_id', $this->admin->id)
            ->assertJsonPath('data.context_page', '/admin/dashboard');

        $this->assertDatabaseHas('ai_conversations', [
            'title' => 'My Conversation',
            'user_id' => $this->admin->id,
            'ai_agent_id' => $this->agent->id,
        ]);
    }

    public function test_store_assigns_authenticated_user_as_owner(): void
    {
        Passport::actingAs($this->admin, ['*']);

        $response = $this->postJson('/api/v1/ai-conversations', [
            'ai_agent_id' => $this->agent->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.user_id', $this->admin->id);
    }

    public function test_store_creates_conversation_without_title(): void
    {
        Passport::actingAs($this->admin, ['*']);

        $response = $this->postJson('/api/v1/ai-conversations', [
            'ai_agent_id' => $this->agent->id,
        ]);

        $response->assertCreated();

        $displayTitle = $response->json('data.display_title');
        $this->assertStringStartsWith('New Conversation', $displayTitle);
    }

    public function test_store_validates_required_agent_id(): void
    {
        Passport::actingAs($this->admin, ['*']);

        $response = $this->postJson('/api/v1/ai-conversations', [
            'title' => 'Test',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['ai_agent_id']);
    }

    public function test_store_validates_agent_id_exists(): void
    {
        Passport::actingAs($this->admin, ['*']);

        $response = $this->postJson('/api/v1/ai-conversations', [
            'ai_agent_id' => '00000000-0000-0000-0000-000000000000',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['ai_agent_id']);
    }

    public function test_store_validates_agent_id_format(): void
    {
        Passport::actingAs($this->admin, ['*']);

        // Use a valid UUID format that doesn't exist in the database
        $response = $this->postJson('/api/v1/ai-conversations', [
            'ai_agent_id' => '11111111-1111-1111-1111-111111111111',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['ai_agent_id']);
    }

    public function test_store_validates_title_max_length(): void
    {
        Passport::actingAs($this->admin, ['*']);

        $response = $this->postJson('/api/v1/ai-conversations', [
            'title' => str_repeat('a', 256),
            'ai_agent_id' => $this->agent->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['title']);
    }

    public function test_store_includes_loaded_relations_in_response(): void
    {
        Passport::actingAs($this->admin, ['*']);

        $response = $this->postJson('/api/v1/ai-conversations', [
            'ai_agent_id' => $this->agent->id,
        ]);

        $response->assertCreated()
            ->assertJsonStructure([
                'data' => [
                    'user' => ['id', 'name'],
                    'agent' => ['id', 'name', 'icon', 'color'],
                ],
            ]);
    }

    // ─── Show ───────────────────────────────────────────────────

    public function test_show_returns_single_conversation(): void
    {
        Passport::actingAs($this->admin, ['*']);

        $conversation = AiConversation::factory()->create([
            'user_id' => $this->admin->id,
            'ai_agent_id' => $this->agent->id,
            'title' => 'Show Test',
        ]);

        $response = $this->getJson("/api/v1/ai-conversations/{$conversation->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $conversation->id)
            ->assertJsonPath('data.title', 'Show Test')
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'title',
                    'display_title',
                    'user_id',
                    'user',
                    'ai_agent_id',
                    'agent',
                    'message_count',
                    'token_count',
                    'summary',
                    'is_pinned',
                    'is_compactable',
                    'context_page',
                    'last_message_at',
                    'state',
                    'created_at',
                    'updated_at',
                ],
            ]);
    }

    public function test_show_returns_404_for_nonexistent_conversation(): void
    {
        Passport::actingAs($this->admin, ['*']);

        $response = $this->getJson('/api/v1/ai-conversations/00000000-0000-0000-0000-000000000000');

        $response->assertNotFound();
    }

    // ─── Update ─────────────────────────────────────────────────

    public function test_update_modifies_conversation_title(): void
    {
        Passport::actingAs($this->admin, ['*']);

        $conversation = AiConversation::factory()->create([
            'user_id' => $this->admin->id,
            'title' => 'Original Title',
        ]);

        $response = $this->putJson("/api/v1/ai-conversations/{$conversation->id}", [
            'title' => 'Updated Title',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.title', 'Updated Title');

        $this->assertDatabaseHas('ai_conversations', [
            'id' => $conversation->id,
            'title' => 'Updated Title',
        ]);
    }

    public function test_update_toggles_is_pinned(): void
    {
        Passport::actingAs($this->admin, ['*']);

        $conversation = AiConversation::factory()->create([
            'user_id' => $this->admin->id,
            'is_pinned' => false,
        ]);

        $response = $this->putJson("/api/v1/ai-conversations/{$conversation->id}", [
            'is_pinned' => true,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.is_pinned', true);
    }

    public function test_update_returns_404_for_nonexistent_conversation(): void
    {
        Passport::actingAs($this->admin, ['*']);

        $response = $this->putJson('/api/v1/ai-conversations/00000000-0000-0000-0000-000000000000', [
            'title' => 'Test',
        ]);

        $response->assertNotFound();
    }

    // ─── Destroy ────────────────────────────────────────────────

    public function test_destroy_soft_deletes_conversation(): void
    {
        Passport::actingAs($this->admin, ['*']);

        $conversation = AiConversation::factory()->create([
            'user_id' => $this->admin->id,
        ]);

        $response = $this->deleteJson("/api/v1/ai-conversations/{$conversation->id}");

        $response->assertNoContent();

        $this->assertSoftDeleted('ai_conversations', ['id' => $conversation->id]);
    }

    public function test_destroy_returns_404_for_nonexistent_conversation(): void
    {
        Passport::actingAs($this->admin, ['*']);

        $response = $this->deleteJson('/api/v1/ai-conversations/00000000-0000-0000-0000-000000000000');

        $response->assertNotFound();
    }

    // ─── Resource Structure ─────────────────────────────────────

    public function test_resource_includes_display_title_fallback(): void
    {
        Passport::actingAs($this->admin, ['*']);

        // Observer auto-sets title to "New Conversation — {datetime}" when null
        $conversation = AiConversation::factory()->create([
            'user_id' => $this->admin->id,
            'title' => null,
        ]);

        $response = $this->getJson("/api/v1/ai-conversations/{$conversation->id}");

        $response->assertOk();

        $displayTitle = $response->json('data.display_title');
        $this->assertStringStartsWith('New Conversation', $displayTitle);
    }

    public function test_resource_includes_state_value(): void
    {
        Passport::actingAs($this->admin, ['*']);

        $conversation = AiConversation::factory()->active()->create([
            'user_id' => $this->admin->id,
        ]);

        $response = $this->getJson("/api/v1/ai-conversations/{$conversation->id}");

        $response->assertOk();

        $this->assertNotNull($response->json('data.state'));
        $this->assertIsString($response->json('data.state'));
    }
}
