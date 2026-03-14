<?php

namespace Aicl\Tests\Feature\Entities;

use Aicl\Enums\AiProvider;
use Aicl\Models\AiAgent;
use Aicl\States\AiAgent\Active;
use Aicl\States\AiAgent\Archived;
use Aicl\States\AiAgent\Draft;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiAgentTest extends TestCase
{
    use RefreshDatabase;

    // Observer registered via AiclServiceProvider::boot()

    // ─── Model & Factory ────────────────────────────────────────

    public function test_can_create_ai_agent(): void
    {
        $agent = AiAgent::factory()->create([
            'name' => 'Test Agent',
            'provider' => AiProvider::OpenAi,
            'model' => 'gpt-4o',
        ]);

        $this->assertDatabaseHas('ai_agents', [
            'id' => $agent->id,
            'name' => 'Test Agent',
            'provider' => 'openai',
            'model' => 'gpt-4o',
        ]);
    }

    public function test_factory_default_state_is_draft(): void
    {
        $agent = AiAgent::factory()->create();

        $this->assertInstanceOf(Draft::class, $agent->state);
    }

    public function test_factory_active_state(): void
    {
        $agent = AiAgent::factory()->active()->create();

        $this->assertInstanceOf(Active::class, $agent->state);
        $this->assertTrue($agent->is_active);
    }

    public function test_factory_archived_state(): void
    {
        $agent = AiAgent::factory()->archived()->create();

        $this->assertInstanceOf(Archived::class, $agent->state);
        $this->assertFalse($agent->is_active);
    }

    public function test_factory_for_provider(): void
    {
        $agent = AiAgent::factory()->forProvider(AiProvider::Anthropic)->create();

        $this->assertEquals(AiProvider::Anthropic, $agent->provider);
        $this->assertContains($agent->model, ['claude-sonnet-4-20250514', 'claude-haiku-4-5-20251001']);
    }

    // ─── Casts ──────────────────────────────────────────────────

    public function test_provider_is_cast_to_enum(): void
    {
        $agent = AiAgent::factory()->create(['provider' => AiProvider::Anthropic]);

        $this->assertInstanceOf(AiProvider::class, $agent->provider);
        $this->assertEquals(AiProvider::Anthropic, $agent->provider);
    }

    public function test_json_fields_are_cast_to_arrays(): void
    {
        $prompts = ['Hello', 'Help me'];
        $capabilities = ['chat', 'summarize'];
        $roles = ['admin'];

        $agent = AiAgent::factory()->create([
            'suggested_prompts' => $prompts,
            'capabilities' => $capabilities,
            'visible_to_roles' => $roles,
        ]);

        $agent->refresh();

        $this->assertIsArray($agent->suggested_prompts);
        $this->assertEquals($prompts, $agent->suggested_prompts);
        $this->assertIsArray($agent->capabilities);
        $this->assertEquals($capabilities, $agent->capabilities);
        $this->assertIsArray($agent->visible_to_roles);
        $this->assertEquals($roles, $agent->visible_to_roles);
    }

    public function test_boolean_cast(): void
    {
        $agent = AiAgent::factory()->create(['is_active' => true]);

        $this->assertIsBool($agent->is_active);
        $this->assertTrue($agent->is_active);
    }

    // ─── Observer ───────────────────────────────────────────────

    public function test_slug_auto_generated_from_name(): void
    {
        $agent = AiAgent::factory()->create([
            'name' => 'My Custom Agent',
            'slug' => '',
        ]);

        $this->assertEquals('my-custom-agent', $agent->slug);
    }

    public function test_slug_not_overwritten_if_provided(): void
    {
        $agent = AiAgent::factory()->create([
            'name' => 'My Agent',
            'slug' => 'custom-slug',
        ]);

        $this->assertEquals('custom-slug', $agent->slug);
    }

    // ─── State Machine ──────────────────────────────────────────

    public function test_draft_can_transition_to_active(): void
    {
        $agent = AiAgent::factory()->create(['state' => Draft::class]);

        $agent->state->transitionTo(Active::class);

        $this->assertInstanceOf(Active::class, $agent->refresh()->state);
    }

    public function test_active_can_transition_to_archived(): void
    {
        $agent = AiAgent::factory()->active()->create();

        $agent->state->transitionTo(Archived::class);

        $this->assertInstanceOf(Archived::class, $agent->refresh()->state);
    }

    public function test_archived_can_transition_to_active(): void
    {
        $agent = AiAgent::factory()->archived()->create();

        $agent->state->transitionTo(Active::class);

        $this->assertInstanceOf(Active::class, $agent->refresh()->state);
    }

    // ─── Scopes ─────────────────────────────────────────────────

    public function test_for_widget_scope_returns_active_agents(): void
    {
        AiAgent::factory()->active()->create(['sort_order' => 2]);
        AiAgent::factory()->active()->create(['sort_order' => 1]);
        AiAgent::factory()->create(); // draft

        $results = AiAgent::query()->forWidget()->get();

        $this->assertCount(2, $results);
        $this->assertEquals(1, $results->first()->sort_order);
    }

    public function test_visible_to_roles_scope(): void
    {
        AiAgent::factory()->create(['visible_to_roles' => null]); // visible to all
        AiAgent::factory()->create(['visible_to_roles' => ['admin']]);
        AiAgent::factory()->create(['visible_to_roles' => ['editor']]);

        $results = AiAgent::query()->visibleToRoles(['admin'])->get();

        $this->assertCount(2, $results); // null + admin
    }

    // ─── Accessors ──────────────────────────────────────────────

    public function test_display_name_accessor(): void
    {
        $agent = AiAgent::factory()->create(['name' => 'My Agent']);

        $this->assertEquals('My Agent', $agent->display_name);
    }

    // ─── Helpers ────────────────────────────────────────────────

    public function test_is_visible_to_returns_true_when_roles_null(): void
    {
        $agent = AiAgent::factory()->create(['visible_to_roles' => null]);

        $this->assertTrue($agent->isVisibleTo(['admin']));
        $this->assertTrue($agent->isVisibleTo([]));
    }

    public function test_is_visible_to_checks_role_intersection(): void
    {
        $agent = AiAgent::factory()->create(['visible_to_roles' => ['admin', 'editor']]);

        $this->assertTrue($agent->isVisibleTo(['admin']));
        $this->assertTrue($agent->isVisibleTo(['editor', 'viewer']));
        $this->assertFalse($agent->isVisibleTo(['viewer']));
    }

    // ─── Soft Deletes ───────────────────────────────────────────

    public function test_soft_delete(): void
    {
        $agent = AiAgent::factory()->create();

        $agent->delete();

        $this->assertSoftDeleted('ai_agents', ['id' => $agent->id]);
    }

    // ─── Searchable Columns ─────────────────────────────────────

    public function test_search_scope_finds_by_name(): void
    {
        AiAgent::factory()->create(['name' => 'Data Analyst']);
        AiAgent::factory()->create(['name' => 'Code Helper']);

        $results = AiAgent::query()->search('analyst')->get();

        $this->assertCount(1, $results);
        $this->assertEquals('Data Analyst', $results->first()->name);
    }

    public function test_search_scope_finds_by_slug(): void
    {
        AiAgent::factory()->create(['slug' => 'data-analyst', 'name' => 'Agent A']);
        AiAgent::factory()->create(['slug' => 'code-helper', 'name' => 'Agent B']);

        $results = AiAgent::query()->search('data-analyst')->get();

        $this->assertCount(1, $results);
    }

    public function test_search_scope_finds_by_description(): void
    {
        AiAgent::factory()->create(['description' => 'Analyzes financial reports', 'name' => 'Agent A', 'slug' => 'a']);
        AiAgent::factory()->create(['description' => 'Writes Python code', 'name' => 'Agent B', 'slug' => 'b']);

        $results = AiAgent::query()->search('financial')->get();

        $this->assertCount(1, $results);
    }
}
