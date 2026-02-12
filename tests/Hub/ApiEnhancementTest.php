<?php

namespace Aicl\Tests\Hub;

use Aicl\Enums\FailureCategory;
use Aicl\Enums\FailureSeverity;
use Aicl\Models\PreventionRule;
use Aicl\Models\RlmFailure;
use Aicl\Models\RlmLesson;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ApiEnhancementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        $this->seedPermissions();

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
        $this->admin->assignRole(Role::findByName('admin', 'api'));
    }

    protected function seedPermissions(): void
    {
        $entities = ['RlmFailure', 'RlmLesson', 'PreventionRule'];
        $actions = ['ViewAny', 'View', 'Create', 'Update', 'Delete'];

        foreach ($entities as $entity) {
            foreach ($actions as $action) {
                Permission::firstOrCreate(['name' => "{$action}:{$entity}", 'guard_name' => 'web']);
                Permission::firstOrCreate(['name' => "{$action}:{$entity}", 'guard_name' => 'api']);
            }
        }

        $adminWeb = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $adminWeb->syncPermissions(Permission::where('guard_name', 'web')->get());

        $adminApi = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'api']);
        $adminApi->syncPermissions(Permission::where('guard_name', 'api')->get());
    }

    // --- RlmFailure Upsert Tests ---

    public function test_upsert_creates_new_failure(): void
    {
        Passport::actingAs($this->admin, [], 'api');

        $response = $this->postJson('/api/v1/rlm_failures/upsert', [
            'failure_code' => 'TEST-001',
            'category' => FailureCategory::Scaffolding->value,
            'severity' => FailureSeverity::High->value,
            'title' => 'Test failure',
            'description' => 'A test failure for upsert.',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('rlm_failures', [
            'failure_code' => 'TEST-001',
            'title' => 'Test failure',
            'report_count' => 1,
        ]);
    }

    public function test_upsert_updates_existing_failure(): void
    {
        Passport::actingAs($this->admin, [], 'api');

        $failure = RlmFailure::factory()->create([
            'failure_code' => 'TEST-002',
            'title' => 'Original title',
            'report_count' => 5,
            'owner_id' => $this->admin->id,
        ]);

        $response = $this->postJson('/api/v1/rlm_failures/upsert', [
            'failure_code' => 'TEST-002',
            'category' => FailureCategory::Scaffolding->value,
            'severity' => FailureSeverity::Medium->value,
            'title' => 'Updated title',
            'description' => 'Updated description.',
        ]);

        $response->assertStatus(200);
        $failure->refresh();
        $this->assertEquals('Updated title', $failure->title);
        $this->assertEquals(6, $failure->report_count); // incremented
    }

    // --- RlmFailure Top Tests ---

    public function test_top_returns_most_reported_failures(): void
    {
        Passport::actingAs($this->admin, [], 'api');

        RlmFailure::factory()->create(['report_count' => 10, 'owner_id' => $this->admin->id]);
        RlmFailure::factory()->create(['report_count' => 5, 'owner_id' => $this->admin->id]);
        RlmFailure::factory()->create(['report_count' => 20, 'owner_id' => $this->admin->id]);

        $response = $this->getJson('/api/v1/rlm_failures/top?limit=2');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(2, $data);
        $this->assertEquals(20, $data[0]['report_count']);
        $this->assertEquals(10, $data[1]['report_count']);
    }

    public function test_top_limits_to_50(): void
    {
        Passport::actingAs($this->admin, [], 'api');

        $response = $this->getJson('/api/v1/rlm_failures/top?limit=100');

        $response->assertStatus(200);
    }

    // --- RlmLesson Search Tests ---

    public function test_lesson_search_finds_by_summary(): void
    {
        Passport::actingAs($this->admin, [], 'api');

        RlmLesson::factory()->create([
            'summary' => 'Always use HasUuids trait for cross-project sync',
            'topic' => 'entity',
            'owner_id' => $this->admin->id,
        ]);
        RlmLesson::factory()->create([
            'summary' => 'PostgreSQL LIKE is case-sensitive',
            'topic' => 'database',
            'owner_id' => $this->admin->id,
        ]);

        $response = $this->getJson('/api/v1/rlm_lessons/search?q=HasUuids');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertStringContainsString('HasUuids', $data[0]['summary']);
    }

    public function test_lesson_search_returns_all_when_empty_query(): void
    {
        Passport::actingAs($this->admin, [], 'api');

        RlmLesson::factory()->count(3)->create(['owner_id' => $this->admin->id]);

        $response = $this->getJson('/api/v1/rlm_lessons/search');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(3, $data);
    }

    public function test_lesson_search_is_case_insensitive(): void
    {
        Passport::actingAs($this->admin, [], 'api');

        RlmLesson::factory()->create([
            'summary' => 'Filament Section namespace gotcha',
            'topic' => 'filament',
            'owner_id' => $this->admin->id,
        ]);

        $response = $this->getJson('/api/v1/rlm_lessons/search?q=filament+section');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
    }

    // --- PreventionRule ForEntity Tests ---

    public function test_for_entity_returns_matching_rules(): void
    {
        Passport::actingAs($this->admin, [], 'api');

        PreventionRule::factory()->create([
            'trigger_context' => ['has_states' => true],
            'rule_text' => 'Always define default state',
            'is_active' => true,
            'confidence' => 0.9,
            'owner_id' => $this->admin->id,
        ]);
        PreventionRule::factory()->create([
            'trigger_context' => ['has_uuid' => true],
            'rule_text' => 'Use HasUuids trait',
            'is_active' => true,
            'confidence' => 0.8,
            'owner_id' => $this->admin->id,
        ]);

        $response = $this->getJson('/api/v1/prevention_rules/for-entity?has_states=true');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertStringContainsString('default state', $data[0]['rule_text']);
    }

    public function test_for_entity_excludes_inactive_rules(): void
    {
        Passport::actingAs($this->admin, [], 'api');

        PreventionRule::factory()->create([
            'trigger_context' => ['has_states' => true],
            'rule_text' => 'Inactive rule',
            'is_active' => false,
            'owner_id' => $this->admin->id,
        ]);

        $response = $this->getJson('/api/v1/prevention_rules/for-entity?has_states=true');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(0, $data);
    }

    public function test_for_entity_orders_by_confidence_then_priority(): void
    {
        Passport::actingAs($this->admin, [], 'api');

        PreventionRule::factory()->create([
            'trigger_context' => ['has_enums' => true],
            'rule_text' => 'Low confidence',
            'is_active' => true,
            'confidence' => 0.3,
            'priority' => 10,
            'owner_id' => $this->admin->id,
        ]);
        PreventionRule::factory()->create([
            'trigger_context' => ['has_enums' => true],
            'rule_text' => 'High confidence',
            'is_active' => true,
            'confidence' => 0.9,
            'priority' => 5,
            'owner_id' => $this->admin->id,
        ]);

        $response = $this->getJson('/api/v1/prevention_rules/for-entity?has_enums=true');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(2, $data);
        $this->assertStringContainsString('High confidence', $data[0]['rule_text']);
    }
}
