<?php

namespace Aicl\Tests\Feature\Http;

use Aicl\Models\GoldenAnnotation;
use Aicl\Models\PreventionRule;
use Aicl\Models\RlmFailure;
use Aicl\Models\RlmLesson;
use Aicl\Models\RlmScore;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Laravel\Passport\Passport;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * S-010: Happy-path functional tests for the RLM Knowledge API endpoints.
 *
 * Covers: search, recall, getFailure — endpoints not covered by ApiCrudTest.
 */
class ApiKnowledgeEndpointTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        Http::fake(['*' => Http::response('', 500)]); // ES unavailable

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

    // ========================================================================
    // GET /api/v1/rlm/search
    // ========================================================================

    public function test_search_returns_matching_failures(): void
    {
        Passport::actingAs($this->user, [], 'api');

        RlmFailure::factory()->create([
            'title' => 'PostgreSQL indexing error',
            'description' => 'LIKE query is case-sensitive',
            'is_active' => true,
            'owner_id' => $this->user->id,
        ]);

        $response = $this->getJson('/api/v1/rlm/search?q=PostgreSQL&type=failure');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data',
            'meta' => ['total', 'search_available'],
        ]);
        $this->assertGreaterThanOrEqual(1, $response->json('meta.total'));
    }

    public function test_search_returns_matching_lessons(): void
    {
        Passport::actingAs($this->user, [], 'api');

        RlmLesson::factory()->create([
            'topic' => 'filament',
            'summary' => 'Xylophone unique summary for search test',
            'is_active' => true,
            'owner_id' => $this->user->id,
        ]);

        $response = $this->getJson('/api/v1/rlm/search?q=Xylophone&type=lesson');

        $response->assertStatus(200);
        $this->assertGreaterThanOrEqual(1, $response->json('meta.total'));
    }

    public function test_search_validates_query_parameter(): void
    {
        Passport::actingAs($this->user, [], 'api');

        $response = $this->getJson('/api/v1/rlm/search');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['q']);
    }

    public function test_search_validates_type_parameter(): void
    {
        Passport::actingAs($this->user, [], 'api');

        $response = $this->getJson('/api/v1/rlm/search?q=test&type=invalid_type');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['type']);
    }

    // ========================================================================
    // GET /api/v1/rlm/recall
    // ========================================================================

    public function test_recall_returns_structured_context(): void
    {
        Passport::actingAs($this->user, [], 'api');

        RlmFailure::factory()->create([
            'is_active' => true,
            'title' => 'Recall test failure',
            'owner_id' => $this->user->id,
        ]);
        RlmLesson::factory()->create([
            'topic' => 'scaffolder',
            'is_active' => true,
            'owner_id' => $this->user->id,
        ]);
        PreventionRule::factory()->withoutFailure()->create([
            'is_active' => true,
            'owner_id' => $this->user->id,
        ]);
        GoldenAnnotation::factory()->universal()->create([
            'is_active' => true,
            'owner_id' => $this->user->id,
        ]);

        $response = $this->getJson('/api/v1/rlm/recall?agent=architect&phase=3');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'failures',
                'lessons',
                'scores',
                'prevention_rules',
                'golden_annotations',
                'risk_briefing' => [
                    'high_risk',
                    'prevention_rules',
                    'recent_outcomes',
                ],
            ],
        ]);
    }

    public function test_recall_with_entity_context(): void
    {
        Passport::actingAs($this->user, [], 'api');

        PreventionRule::factory()->create([
            'trigger_context' => ['has_states' => true],
            'rule_text' => 'State transition rule',
            'is_active' => true,
            'owner_id' => $this->user->id,
        ]);

        $entityContext = json_encode(['has_states' => true]);
        $response = $this->getJson("/api/v1/rlm/recall?agent=architect&phase=3&entity_context={$entityContext}");

        $response->assertStatus(200);
        $rules = collect($response->json('data.prevention_rules'));
        $this->assertTrue($rules->contains('rule_text', 'State transition rule'));
    }

    public function test_recall_with_entity_name_includes_scores(): void
    {
        Passport::actingAs($this->user, [], 'api');

        RlmScore::factory()->create([
            'entity_name' => 'Invoice',
            'percentage' => 95.24,
            'owner_id' => $this->user->id,
        ]);

        $response = $this->getJson('/api/v1/rlm/recall?agent=architect&phase=3&entity_name=Invoice');

        $response->assertStatus(200);
        $scores = $response->json('data.scores');
        $this->assertNotEmpty($scores);
    }

    public function test_recall_validates_required_parameters(): void
    {
        Passport::actingAs($this->user, [], 'api');

        $response = $this->getJson('/api/v1/rlm/recall');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['agent', 'phase']);
    }

    // ========================================================================
    // GET /api/v1/rlm/failure/{failureCode}
    // ========================================================================

    public function test_get_failure_returns_record_by_code(): void
    {
        Passport::actingAs($this->user, [], 'api');

        $failure = RlmFailure::factory()->create([
            'failure_code' => 'API-TEST-001',
            'title' => 'API test failure',
            'owner_id' => $this->user->id,
        ]);

        $response = $this->getJson('/api/v1/rlm/failure/API-TEST-001');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
        $response->assertJsonPath('data.failure_code', 'API-TEST-001');
    }

    public function test_get_failure_returns_404_for_nonexistent_code(): void
    {
        Passport::actingAs($this->user, [], 'api');

        $response = $this->getJson('/api/v1/rlm/failure/NONEXISTENT-999');

        $response->assertStatus(404);
        $response->assertJsonStructure(['error']);
    }
}
