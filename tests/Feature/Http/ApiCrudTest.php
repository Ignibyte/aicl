<?php

namespace Aicl\Tests\Feature\Http;

use Aicl\Enums\FailureCategory;
use Aicl\Enums\FailureSeverity;
use Aicl\Enums\ScoreType;
use Aicl\Models\FailureReport;
use Aicl\Models\GenerationTrace;
use Aicl\Models\PreventionRule;
use Aicl\Models\RlmFailure;
use Aicl\Models\RlmLesson;
use Aicl\Models\RlmPattern;
use Aicl\Models\RlmScore;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Passport\Passport;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ApiCrudTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $this->user = User::factory()->create(['id' => 1]);

        $entities = ['RlmPattern', 'RlmFailure', 'RlmLesson', 'FailureReport', 'GenerationTrace', 'PreventionRule', 'RlmScore'];
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

        $this->user->assignRole('admin');
        $this->user->assignRole(Role::findByName('admin', 'api'));
    }

    /**
     * @return array<string, array{0: string, 1: class-string, 2: array<string, mixed>}>
     */
    public static function crudEntityProvider(): array
    {
        return [
            'RlmPattern' => ['rlm_patterns', RlmPattern::class, [
                'name' => 'test_crud_pattern',
                'description' => 'Test description for CRUD',
                'target' => 'model',
                'check_regex' => '/test/',
                'severity' => 'warning',
                'category' => 'structural',
                'source' => 'manual',
            ]],
            'RlmFailure' => ['rlm_failures', RlmFailure::class, [
                'failure_code' => 'CRUD-TEST-001',
                'category' => 'scaffolding',
                'title' => 'Test CRUD failure',
                'description' => 'Test description for CRUD failure',
                'severity' => 'medium',
            ]],
            'RlmLesson' => ['rlm_lessons', RlmLesson::class, [
                'topic' => 'testing',
                'summary' => 'Test CRUD summary',
                'detail' => 'Test detail text for CRUD',
            ]],
            'GenerationTrace' => ['generation_traces', GenerationTrace::class, [
                'entity_name' => 'TestEntity',
                'scaffolder_args' => '--fields="name:string" --no-interaction',
            ]],
            'PreventionRule' => ['prevention_rules', PreventionRule::class, [
                'rule_text' => 'Always check for null before accessing property',
                'priority' => 5,
            ]],
            'RlmScore' => ['rlm_scores', RlmScore::class, [
                'entity_name' => 'TestEntity',
                'score_type' => 'structural',
                'passed' => 40,
                'total' => 42,
                'percentage' => 95.24,
            ]],
        ];
    }

    /**
     * @param  class-string  $modelClass
     * @param  array<string, mixed>  $storeData
     */
    #[DataProvider('crudEntityProvider')]
    public function test_index_returns_paginated_list(string $resource, string $modelClass, array $storeData): void
    {
        Passport::actingAs($this->user, [], 'api');

        $modelClass::factory()->count(3)->create(['owner_id' => $this->user->id]);

        $response = $this->getJson("/api/v1/{$resource}");

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
        $this->assertCount(3, $response->json('data'));
    }

    /**
     * @param  class-string  $modelClass
     * @param  array<string, mixed>  $storeData
     */
    #[DataProvider('crudEntityProvider')]
    public function test_store_creates_record(string $resource, string $modelClass, array $storeData): void
    {
        Passport::actingAs($this->user, [], 'api');

        // FailureReport needs a real rlm_failure_id
        if ($modelClass === FailureReport::class) {
            $failure = RlmFailure::factory()->create(['owner_id' => $this->user->id]);
            $storeData['rlm_failure_id'] = $failure->id;
            $storeData['project_hash'] = 'abc123hash';
            $storeData['entity_name'] = 'TestEntity';
            $storeData['reported_at'] = now()->toDateTimeString();
        }

        $response = $this->postJson("/api/v1/{$resource}", $storeData);

        $response->assertStatus(201);
        $response->assertJsonStructure(['data']);
    }

    /**
     * @param  class-string  $modelClass
     * @param  array<string, mixed>  $storeData
     */
    #[DataProvider('crudEntityProvider')]
    public function test_show_returns_record(string $resource, string $modelClass, array $storeData): void
    {
        Passport::actingAs($this->user, [], 'api');

        $record = $modelClass::factory()->create(['owner_id' => $this->user->id]);

        $response = $this->getJson("/api/v1/{$resource}/{$record->id}");

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    /**
     * @param  class-string  $modelClass
     * @param  array<string, mixed>  $storeData
     */
    #[DataProvider('crudEntityProvider')]
    public function test_update_modifies_record(string $resource, string $modelClass, array $storeData): void
    {
        Passport::actingAs($this->user, [], 'api');

        $record = $modelClass::factory()->create(['owner_id' => $this->user->id]);

        // Build update data based on entity type
        $updateData = match ($modelClass) {
            RlmPattern::class => ['description' => 'Updated description via API test'],
            RlmFailure::class => ['title' => 'Updated failure title via API'],
            RlmLesson::class => ['summary' => 'Updated summary via API'],
            FailureReport::class => ['resolution_notes' => 'Fixed via API test'],
            GenerationTrace::class => ['test_results' => 'Tests: 20 passed (30 assertions)'],
            PreventionRule::class => ['rule_text' => 'Updated rule text via API test'],
            RlmScore::class => ['percentage' => 100.00],
            default => [],
        };

        $response = $this->putJson("/api/v1/{$resource}/{$record->id}", $updateData);

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    /**
     * @param  class-string  $modelClass
     * @param  array<string, mixed>  $storeData
     */
    #[DataProvider('crudEntityProvider')]
    public function test_destroy_deletes_record(string $resource, string $modelClass, array $storeData): void
    {
        Passport::actingAs($this->user, [], 'api');

        $record = $modelClass::factory()->create(['owner_id' => $this->user->id]);

        $response = $this->deleteJson("/api/v1/{$resource}/{$record->id}");

        $response->assertStatus(200);
        $response->assertJson(['message' => class_basename($modelClass).' deleted.']);
    }

    // --- Non-Provider Tests ---

    public function test_unauthenticated_request_returns_401(): void
    {
        $response = $this->getJson('/api/v1/rlm_patterns');

        $response->assertStatus(401);
    }

    public function test_rlm_failure_upsert_creates_new(): void
    {
        Passport::actingAs($this->user, [], 'api');

        $response = $this->postJson('/api/v1/rlm_failures/upsert', [
            'failure_code' => 'UPSERT-NEW-001',
            'category' => FailureCategory::Scaffolding->value,
            'severity' => FailureSeverity::High->value,
            'title' => 'New upsert failure',
            'description' => 'Created via upsert endpoint.',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('rlm_failures', [
            'failure_code' => 'UPSERT-NEW-001',
            'title' => 'New upsert failure',
            'report_count' => 1,
        ]);
    }

    public function test_rlm_failure_upsert_updates_existing(): void
    {
        Passport::actingAs($this->user, [], 'api');

        RlmFailure::factory()->create([
            'failure_code' => 'UPSERT-EXIST-001',
            'title' => 'Original upsert title',
            'report_count' => 3,
            'owner_id' => $this->user->id,
        ]);

        $response = $this->postJson('/api/v1/rlm_failures/upsert', [
            'failure_code' => 'UPSERT-EXIST-001',
            'category' => FailureCategory::Testing->value,
            'severity' => FailureSeverity::Low->value,
            'title' => 'Updated upsert title',
            'description' => 'Updated via upsert.',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('rlm_failures', [
            'failure_code' => 'UPSERT-EXIST-001',
            'title' => 'Updated upsert title',
            'report_count' => 4,
        ]);
    }

    public function test_rlm_failure_top_returns_ordered(): void
    {
        Passport::actingAs($this->user, [], 'api');

        RlmFailure::factory()->create(['report_count' => 15, 'owner_id' => $this->user->id]);
        RlmFailure::factory()->create(['report_count' => 3, 'owner_id' => $this->user->id]);
        RlmFailure::factory()->create(['report_count' => 30, 'owner_id' => $this->user->id]);

        $response = $this->getJson('/api/v1/rlm_failures/top?limit=3');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(3, $data);
        $this->assertEquals(30, $data[0]['report_count']);
        $this->assertEquals(15, $data[1]['report_count']);
        $this->assertEquals(3, $data[2]['report_count']);
    }

    public function test_rlm_lesson_search_returns_results(): void
    {
        Passport::actingAs($this->user, [], 'api');

        RlmLesson::factory()->create([
            'summary' => 'Unique searchable lesson about PostgreSQL indexing',
            'topic' => 'database',
            'owner_id' => $this->user->id,
        ]);
        RlmLesson::factory()->create([
            'summary' => 'Another lesson about Filament forms',
            'topic' => 'filament',
            'owner_id' => $this->user->id,
        ]);

        $response = $this->getJson('/api/v1/rlm_lessons/search?q=PostgreSQL');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertStringContainsString('PostgreSQL', $data[0]['summary']);
    }

    public function test_prevention_rule_for_entity_returns_active(): void
    {
        Passport::actingAs($this->user, [], 'api');

        PreventionRule::factory()->create([
            'trigger_context' => ['has_states' => true],
            'rule_text' => 'Verify state transitions match design blueprint',
            'is_active' => true,
            'confidence' => 0.95,
            'priority' => 8,
            'owner_id' => $this->user->id,
        ]);
        PreventionRule::factory()->create([
            'trigger_context' => ['has_uuid' => true],
            'rule_text' => 'Use HasUuids trait for UUID primary keys',
            'is_active' => true,
            'confidence' => 0.85,
            'owner_id' => $this->user->id,
        ]);
        PreventionRule::factory()->create([
            'trigger_context' => ['has_states' => true],
            'rule_text' => 'Inactive state rule should not appear',
            'is_active' => false,
            'owner_id' => $this->user->id,
        ]);

        $response = $this->getJson('/api/v1/prevention_rules/for-entity?has_states=true');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertStringContainsString('state transitions', $data[0]['rule_text']);
    }

    public function test_store_failure_report_with_valid_failure_id(): void
    {
        Passport::actingAs($this->user, [], 'api');

        $failure = RlmFailure::factory()->create(['owner_id' => $this->user->id]);

        $response = $this->postJson('/api/v1/failure_reports', [
            'rlm_failure_id' => $failure->id,
            'project_hash' => 'testhash123',
            'entity_name' => 'Invoice',
            'reported_at' => now()->toDateTimeString(),
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure(['data' => ['id', 'project_hash', 'entity_name']]);
    }

    public function test_store_generation_trace_with_required_fields(): void
    {
        Passport::actingAs($this->user, [], 'api');

        $response = $this->postJson('/api/v1/generation_traces', [
            'entity_name' => 'Project',
            'scaffolder_args' => '--fields="name:string,status:enum:ProjectStatus" --widgets --no-interaction',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.entity_name', 'Project');
    }

    public function test_store_rlm_score_with_all_required_fields(): void
    {
        Passport::actingAs($this->user, [], 'api');

        $response = $this->postJson('/api/v1/rlm_scores', [
            'entity_name' => 'Task',
            'score_type' => ScoreType::Structural->value,
            'passed' => 42,
            'total' => 42,
            'percentage' => 100.00,
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.entity_name', 'Task');
        $this->assertEquals(100.0, (float) $response->json('data.percentage'));
    }
}
