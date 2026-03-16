<?php

namespace Aicl\Tests\Feature\Api;

use Aicl\Enums\AiProvider;
use Aicl\Events\EntityCreated;
use Aicl\Events\EntityDeleted;
use Aicl\Events\EntityUpdated;
use Aicl\Models\AiAgent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Passport\Passport;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AiAgentControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $viewer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPermissionsAndRoles();

        Event::fake([EntityCreated::class, EntityUpdated::class, EntityDeleted::class]);

        $this->admin = User::factory()->create();
        $this->admin->assignRole(Role::findByName('admin', 'api'));

        $this->viewer = User::factory()->create();
        $this->viewer->assignRole(Role::findByName('viewer', 'api'));
    }

    /**
     * Create AiAgent permissions on the api guard and assign to roles.
     * Shield's `shield:generate` creates these in production; tests must
     * seed them manually since RefreshDatabase wipes the permissions table.
     */
    private function seedPermissionsAndRoles(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $actions = ['ViewAny', 'View', 'Create', 'Update', 'Delete'];

        foreach ($actions as $action) {
            Permission::firstOrCreate([
                'name' => "{$action}:AiAgent",
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
        $response = $this->getJson('/api/v1/ai-agents');

        $response->assertUnauthorized();
    }

    public function test_store_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/ai-agents', []);

        $response->assertUnauthorized();
    }

    public function test_show_requires_authentication(): void
    {
        $agent = AiAgent::factory()->create();

        $response = $this->getJson("/api/v1/ai-agents/{$agent->id}");

        $response->assertUnauthorized();
    }

    public function test_update_requires_authentication(): void
    {
        $agent = AiAgent::factory()->create();

        $response = $this->putJson("/api/v1/ai-agents/{$agent->id}", []);

        $response->assertUnauthorized();
    }

    public function test_destroy_requires_authentication(): void
    {
        $agent = AiAgent::factory()->create();

        $response = $this->deleteJson("/api/v1/ai-agents/{$agent->id}");

        $response->assertUnauthorized();
    }

    // ─── Authorization ──────────────────────────────────────────

    public function test_viewer_can_list_agents(): void
    {
        Passport::actingAs($this->viewer, ['*']);

        AiAgent::factory()->count(2)->create();

        $response = $this->getJson('/api/v1/ai-agents');

        $response->assertOk();
    }

    public function test_viewer_can_view_single_agent(): void
    {
        Passport::actingAs($this->viewer, ['*']);

        $agent = AiAgent::factory()->create();

        $response = $this->getJson("/api/v1/ai-agents/{$agent->id}");

        $response->assertOk();
    }

    public function test_viewer_cannot_create_agent(): void
    {
        Passport::actingAs($this->viewer, ['*']);

        $response = $this->postJson('/api/v1/ai-agents', [
            'name' => 'Test Agent',
            'slug' => 'test-agent',
            'provider' => 'openai',
            'model' => 'gpt-4o',
        ]);

        $response->assertForbidden();
    }

    public function test_viewer_cannot_update_agent(): void
    {
        Passport::actingAs($this->viewer, ['*']);

        $agent = AiAgent::factory()->create();

        $response = $this->putJson("/api/v1/ai-agents/{$agent->id}", [
            'name' => 'Updated Name',
        ]);

        $response->assertForbidden();
    }

    public function test_viewer_cannot_delete_agent(): void
    {
        Passport::actingAs($this->viewer, ['*']);

        $agent = AiAgent::factory()->create();

        $response = $this->deleteJson("/api/v1/ai-agents/{$agent->id}");

        $response->assertForbidden();
    }

    // ─── Index ──────────────────────────────────────────────────

    public function test_index_returns_paginated_agents(): void
    {
        Passport::actingAs($this->admin, ['*']);

        AiAgent::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/ai-agents');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'slug',
                        'provider',
                        'model',
                        'state',
                        'is_active',
                        'created_at',
                        'updated_at',
                    ],
                ],
                'links',
                'meta',
            ]);

        $this->assertCount(3, $response->json('data'));
    }

    public function test_index_returns_agents_ordered_by_sort_order_then_name(): void
    {
        Passport::actingAs($this->admin, ['*']);

        AiAgent::factory()->create(['name' => 'Zulu Agent', 'sort_order' => 1]);
        AiAgent::factory()->create(['name' => 'Alpha Agent', 'sort_order' => 1]);
        AiAgent::factory()->create(['name' => 'Beta Agent', 'sort_order' => 0]);

        $response = $this->getJson('/api/v1/ai-agents');

        $response->assertOk();

        $names = collect($response->json('data'))->pluck('name')->toArray();
        $this->assertEquals(['Beta Agent', 'Alpha Agent', 'Zulu Agent'], $names);
    }

    public function test_index_returns_empty_collection_when_no_agents(): void
    {
        Passport::actingAs($this->admin, ['*']);

        $response = $this->getJson('/api/v1/ai-agents');

        $response->assertOk()
            ->assertJsonCount(0, 'data');
    }

    // ─── Store ──────────────────────────────────────────────────

    public function test_store_creates_agent_with_required_fields(): void
    {
        Passport::actingAs($this->admin, ['*']);

        $response = $this->postJson('/api/v1/ai-agents', [
            'name' => 'New Agent',
            'slug' => 'new-agent',
            'provider' => 'openai',
            'model' => 'gpt-4o',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'New Agent')
            ->assertJsonPath('data.slug', 'new-agent')
            ->assertJsonPath('data.provider', 'openai')
            ->assertJsonPath('data.model', 'gpt-4o');

        $this->assertDatabaseHas('ai_agents', [
            'name' => 'New Agent',
            'slug' => 'new-agent',
        ]);
    }

    public function test_store_creates_agent_with_all_fields(): void
    {
        Passport::actingAs($this->admin, ['*']);

        $response = $this->postJson('/api/v1/ai-agents', [
            'name' => 'Full Agent',
            'slug' => 'full-agent',
            'description' => 'A fully configured agent.',
            'provider' => 'anthropic',
            'model' => 'claude-sonnet-4-20250514',
            'system_prompt' => 'You are a helpful assistant.',
            'max_tokens' => 4096,
            'temperature' => 0.7,
            'context_window' => 128000,
            'context_messages' => 20,
            'is_active' => true,
            'icon' => 'heroicon-o-cpu-chip',
            'color' => '#ff0000',
            'sort_order' => 5,
            'suggested_prompts' => ['Analyze data', 'Write code'],
            'capabilities' => ['chat', 'analyze_data'],
            'visible_to_roles' => ['admin', 'editor'],
            'max_requests_per_minute' => 30,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Full Agent')
            ->assertJsonPath('data.description', 'A fully configured agent.')
            ->assertJsonPath('data.provider', 'anthropic')
            ->assertJsonPath('data.max_tokens', 4096)
            ->assertJsonPath('data.sort_order', 5);

        $this->assertDatabaseHas('ai_agents', [
            'name' => 'Full Agent',
            'slug' => 'full-agent',
        ]);
    }

    public function test_store_validates_required_name(): void
    {
        Passport::actingAs($this->admin, ['*']);

        $response = $this->postJson('/api/v1/ai-agents', [
            'slug' => 'test',
            'provider' => 'openai',
            'model' => 'gpt-4o',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_store_validates_required_slug(): void
    {
        Passport::actingAs($this->admin, ['*']);

        $response = $this->postJson('/api/v1/ai-agents', [
            'name' => 'Test',
            'provider' => 'openai',
            'model' => 'gpt-4o',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['slug']);
    }

    public function test_store_validates_unique_slug(): void
    {
        Passport::actingAs($this->admin, ['*']);

        AiAgent::factory()->create(['slug' => 'existing-slug']);

        $response = $this->postJson('/api/v1/ai-agents', [
            'name' => 'Another Agent',
            'slug' => 'existing-slug',
            'provider' => 'openai',
            'model' => 'gpt-4o',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['slug']);
    }

    public function test_store_validates_required_provider(): void
    {
        Passport::actingAs($this->admin, ['*']);

        $response = $this->postJson('/api/v1/ai-agents', [
            'name' => 'Test',
            'slug' => 'test',
            'model' => 'gpt-4o',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['provider']);
    }

    public function test_store_validates_provider_enum(): void
    {
        Passport::actingAs($this->admin, ['*']);

        $response = $this->postJson('/api/v1/ai-agents', [
            'name' => 'Test',
            'slug' => 'test',
            'provider' => 'invalid-provider',
            'model' => 'gpt-4o',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['provider']);
    }

    public function test_store_validates_required_model(): void
    {
        Passport::actingAs($this->admin, ['*']);

        $response = $this->postJson('/api/v1/ai-agents', [
            'name' => 'Test',
            'slug' => 'test',
            'provider' => 'openai',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['model']);
    }

    public function test_store_validates_temperature_range(): void
    {
        Passport::actingAs($this->admin, ['*']);

        $response = $this->postJson('/api/v1/ai-agents', [
            'name' => 'Test',
            'slug' => 'test',
            'provider' => 'openai',
            'model' => 'gpt-4o',
            'temperature' => 3.0,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['temperature']);
    }

    public function test_store_validates_max_tokens_min(): void
    {
        Passport::actingAs($this->admin, ['*']);

        $response = $this->postJson('/api/v1/ai-agents', [
            'name' => 'Test',
            'slug' => 'test',
            'provider' => 'openai',
            'model' => 'gpt-4o',
            'max_tokens' => 0,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['max_tokens']);
    }

    // ─── Show ───────────────────────────────────────────────────

    public function test_show_returns_single_agent(): void
    {
        Passport::actingAs($this->admin, ['*']);

        $agent = AiAgent::factory()->create([
            'name' => 'Show Agent',
            'slug' => 'show-agent',
            'provider' => AiProvider::Anthropic,
            'model' => 'claude-sonnet-4-20250514',
        ]);

        $response = $this->getJson("/api/v1/ai-agents/{$agent->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $agent->id)
            ->assertJsonPath('data.name', 'Show Agent')
            ->assertJsonPath('data.slug', 'show-agent')
            ->assertJsonPath('data.provider', 'anthropic')
            ->assertJsonPath('data.model', 'claude-sonnet-4-20250514')
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'slug',
                    'description',
                    'provider',
                    'model',
                    'system_prompt',
                    'max_tokens',
                    'temperature',
                    'context_window',
                    'context_messages',
                    'is_active',
                    'icon',
                    'color',
                    'sort_order',
                    'suggested_prompts',
                    'capabilities',
                    'visible_to_roles',
                    'max_requests_per_minute',
                    'state',
                    'is_configured',
                    'created_at',
                    'updated_at',
                ],
            ]);
    }

    public function test_show_returns_404_for_nonexistent_agent(): void
    {
        Passport::actingAs($this->admin, ['*']);

        $response = $this->getJson('/api/v1/ai-agents/00000000-0000-0000-0000-000000000000');

        $response->assertNotFound();
    }

    // ─── Update ─────────────────────────────────────────────────

    public function test_update_modifies_agent_name(): void
    {
        Passport::actingAs($this->admin, ['*']);

        $agent = AiAgent::factory()->create(['name' => 'Old Name']);

        $response = $this->putJson("/api/v1/ai-agents/{$agent->id}", [
            'name' => 'New Name',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'New Name');

        $this->assertDatabaseHas('ai_agents', [
            'id' => $agent->id,
            'name' => 'New Name',
        ]);
    }

    public function test_update_modifies_multiple_fields(): void
    {
        Passport::actingAs($this->admin, ['*']);

        $agent = AiAgent::factory()->create();

        $response = $this->putJson("/api/v1/ai-agents/{$agent->id}", [
            'name' => 'Updated Agent',
            'description' => 'Updated description.',
            'max_tokens' => 8192,
            'temperature' => 1.5,
            'is_active' => false,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Updated Agent')
            ->assertJsonPath('data.description', 'Updated description.')
            ->assertJsonPath('data.max_tokens', 8192)
            ->assertJsonPath('data.is_active', false);
    }

    public function test_update_validates_temperature_range(): void
    {
        Passport::actingAs($this->admin, ['*']);

        $agent = AiAgent::factory()->create();

        $response = $this->putJson("/api/v1/ai-agents/{$agent->id}", [
            'temperature' => 5.0,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['temperature']);
    }

    public function test_update_returns_404_for_nonexistent_agent(): void
    {
        Passport::actingAs($this->admin, ['*']);

        $response = $this->putJson('/api/v1/ai-agents/00000000-0000-0000-0000-000000000000', [
            'name' => 'Test',
        ]);

        $response->assertNotFound();
    }

    // ─── Destroy ────────────────────────────────────────────────

    public function test_destroy_soft_deletes_agent(): void
    {
        Passport::actingAs($this->admin, ['*']);

        $agent = AiAgent::factory()->create();

        $response = $this->deleteJson("/api/v1/ai-agents/{$agent->id}");

        $response->assertNoContent();

        $this->assertSoftDeleted('ai_agents', ['id' => $agent->id]);
    }

    public function test_destroy_returns_404_for_nonexistent_agent(): void
    {
        Passport::actingAs($this->admin, ['*']);

        $response = $this->deleteJson('/api/v1/ai-agents/00000000-0000-0000-0000-000000000000');

        $response->assertNotFound();
    }

    // ─── Resource Structure ─────────────────────────────────────

    public function test_resource_includes_is_configured_field(): void
    {
        Passport::actingAs($this->admin, ['*']);

        $agent = AiAgent::factory()->create([
            'provider' => AiProvider::OpenAi,
        ]);

        $response = $this->getJson("/api/v1/ai-agents/{$agent->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => ['is_configured'],
            ]);
    }

    public function test_resource_includes_state_value(): void
    {
        Passport::actingAs($this->admin, ['*']);

        $agent = AiAgent::factory()->active()->create();

        $response = $this->getJson("/api/v1/ai-agents/{$agent->id}");

        $response->assertOk();

        $this->assertNotNull($response->json('data.state'));
        $this->assertIsString($response->json('data.state'));
    }
}
