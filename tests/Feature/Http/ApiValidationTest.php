<?php

namespace Aicl\Tests\Feature\Http;

use Aicl\Models\RlmFailure;
use Aicl\Models\RlmPattern;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Passport\Passport;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ApiValidationTest extends TestCase
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

    public function test_store_rlm_failure_requires_failure_code(): void
    {
        Passport::actingAs($this->user, [], 'api');

        $response = $this->postJson('/api/v1/rlm_failures', [
            'category' => 'scaffolding',
            'title' => 'Missing failure code',
            'description' => 'Should fail validation',
            'severity' => 'medium',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['failure_code']);
    }

    public function test_store_rlm_failure_validates_category_enum(): void
    {
        Passport::actingAs($this->user, [], 'api');

        $response = $this->postJson('/api/v1/rlm_failures', [
            'failure_code' => 'VAL-CAT-001',
            'category' => 'nonexistent_category',
            'title' => 'Invalid category',
            'description' => 'Should fail enum validation',
            'severity' => 'medium',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['category']);
    }

    public function test_store_rlm_failure_validates_severity_enum(): void
    {
        Passport::actingAs($this->user, [], 'api');

        $response = $this->postJson('/api/v1/rlm_failures', [
            'failure_code' => 'VAL-SEV-001',
            'category' => 'scaffolding',
            'title' => 'Invalid severity',
            'description' => 'Should fail enum validation',
            'severity' => 'nonexistent_severity',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['severity']);
    }

    public function test_store_rlm_pattern_requires_name(): void
    {
        Passport::actingAs($this->user, [], 'api');

        $response = $this->postJson('/api/v1/rlm_patterns', [
            'description' => 'Missing name field',
            'target' => 'model',
            'check_regex' => '/test/',
            'severity' => 'warning',
            'category' => 'structural',
            'source' => 'manual',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name']);
    }

    public function test_store_rlm_pattern_requires_unique_name(): void
    {
        Passport::actingAs($this->user, [], 'api');

        RlmPattern::factory()->create([
            'name' => 'duplicate_pattern_name',
            'owner_id' => $this->user->id,
        ]);

        $response = $this->postJson('/api/v1/rlm_patterns', [
            'name' => 'duplicate_pattern_name',
            'description' => 'Duplicate name should fail',
            'target' => 'model',
            'check_regex' => '/test/',
            'severity' => 'warning',
            'category' => 'structural',
            'source' => 'manual',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name']);
    }

    public function test_store_rlm_lesson_requires_topic_summary_detail(): void
    {
        Passport::actingAs($this->user, [], 'api');

        $response = $this->postJson('/api/v1/rlm_lessons', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['topic', 'summary', 'detail']);
    }

    public function test_store_failure_report_requires_rlm_failure_id(): void
    {
        Passport::actingAs($this->user, [], 'api');

        $response = $this->postJson('/api/v1/failure_reports', [
            'project_hash' => 'testhash',
            'entity_name' => 'TestEntity',
            'reported_at' => now()->toDateTimeString(),
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['rlm_failure_id']);
    }

    public function test_store_failure_report_validates_rlm_failure_id_exists(): void
    {
        Passport::actingAs($this->user, [], 'api');

        $response = $this->postJson('/api/v1/failure_reports', [
            'rlm_failure_id' => '00000000-0000-0000-0000-000000000000',
            'project_hash' => 'testhash',
            'entity_name' => 'TestEntity',
            'reported_at' => now()->toDateTimeString(),
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['rlm_failure_id']);
    }

    public function test_store_rlm_score_requires_entity_name_and_score_type(): void
    {
        Passport::actingAs($this->user, [], 'api');

        $response = $this->postJson('/api/v1/rlm_scores', [
            'passed' => 40,
            'total' => 42,
            'percentage' => 95.24,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['entity_name', 'score_type']);
    }

    public function test_store_rlm_score_validates_score_type_enum(): void
    {
        Passport::actingAs($this->user, [], 'api');

        $response = $this->postJson('/api/v1/rlm_scores', [
            'entity_name' => 'TestEntity',
            'score_type' => 'invalid_score_type',
            'passed' => 40,
            'total' => 42,
            'percentage' => 95.24,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['score_type']);
    }

    public function test_store_generation_trace_requires_entity_name(): void
    {
        Passport::actingAs($this->user, [], 'api');

        $response = $this->postJson('/api/v1/generation_traces', [
            'scaffolder_args' => '--fields="name:string"',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['entity_name']);
    }

    public function test_store_generation_trace_requires_scaffolder_args(): void
    {
        Passport::actingAs($this->user, [], 'api');

        $response = $this->postJson('/api/v1/generation_traces', [
            'entity_name' => 'TestEntity',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['scaffolder_args']);
    }

    public function test_store_prevention_rule_requires_rule_text_and_priority(): void
    {
        Passport::actingAs($this->user, [], 'api');

        $response = $this->postJson('/api/v1/prevention_rules', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['rule_text', 'priority']);
    }

    public function test_store_prevention_rule_validates_priority_is_integer(): void
    {
        Passport::actingAs($this->user, [], 'api');

        $response = $this->postJson('/api/v1/prevention_rules', [
            'rule_text' => 'Valid rule text',
            'priority' => 'not_a_number',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['priority']);
    }

    public function test_store_rlm_score_requires_passed_total_percentage(): void
    {
        Passport::actingAs($this->user, [], 'api');

        $response = $this->postJson('/api/v1/rlm_scores', [
            'entity_name' => 'TestEntity',
            'score_type' => 'structural',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['passed', 'total', 'percentage']);
    }

    public function test_store_failure_report_requires_project_hash_and_entity_name(): void
    {
        Passport::actingAs($this->user, [], 'api');

        $failure = RlmFailure::factory()->create(['owner_id' => $this->user->id]);

        $response = $this->postJson('/api/v1/failure_reports', [
            'rlm_failure_id' => $failure->id,
            'reported_at' => now()->toDateTimeString(),
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['project_hash', 'entity_name']);
    }

    public function test_store_rlm_pattern_requires_all_mandatory_fields(): void
    {
        Passport::actingAs($this->user, [], 'api');

        $response = $this->postJson('/api/v1/rlm_patterns', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors([
            'name',
            'description',
            'target',
            'check_regex',
            'severity',
            'category',
            'source',
        ]);
    }

    public function test_store_rlm_failure_requires_all_mandatory_fields(): void
    {
        Passport::actingAs($this->user, [], 'api');

        $response = $this->postJson('/api/v1/rlm_failures', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors([
            'failure_code',
            'category',
            'title',
            'description',
            'severity',
        ]);
    }
}
