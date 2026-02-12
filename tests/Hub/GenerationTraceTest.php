<?php

namespace Aicl\Tests\Hub;

use Aicl\Models\GenerationTrace;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class GenerationTraceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $this->seedPermissions();
    }

    protected function seedPermissions(): void
    {
        $permissions = [
            'ViewAny:GenerationTrace', 'View:GenerationTrace', 'Create:GenerationTrace',
            'Update:GenerationTrace', 'Delete:GenerationTrace',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin->syncPermissions(Permission::where('guard_name', 'web')->get());
    }

    public function test_generation_trace_uses_uuid_primary_key(): void
    {
        $record = GenerationTrace::factory()->create();

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $record->id
        );
    }

    public function test_generation_trace_can_be_created(): void
    {
        $owner = User::factory()->create();
        $record = GenerationTrace::factory()->create(['owner_id' => $owner->id]);

        $this->assertDatabaseHas('generation_traces', ['id' => $record->id]);
    }

    public function test_generation_trace_belongs_to_owner(): void
    {
        $owner = User::factory()->create();
        $record = GenerationTrace::factory()->create(['owner_id' => $owner->id]);

        $this->assertTrue($record->owner->is($owner));
    }

    public function test_generation_trace_soft_deletes(): void
    {
        $record = GenerationTrace::factory()->create();
        $record->delete();

        $this->assertSoftDeleted('generation_traces', ['id' => $record->id]);
    }

    public function test_unprocessed_scope_filters_correctly(): void
    {
        GenerationTrace::factory()->create(['is_processed' => false]);
        GenerationTrace::factory()->processed()->create();

        $this->assertCount(1, GenerationTrace::unprocessed()->get());
    }

    public function test_by_entity_scope(): void
    {
        GenerationTrace::factory()->create(['entity_name' => 'Project']);
        GenerationTrace::factory()->create(['entity_name' => 'Task']);

        $this->assertCount(1, GenerationTrace::byEntity('Project')->get());
    }

    public function test_by_project_scope(): void
    {
        $hash = hash('sha256', 'test-project');
        GenerationTrace::factory()->create(['project_hash' => $hash]);
        GenerationTrace::factory()->create(['project_hash' => hash('sha256', 'other-project')]);

        $this->assertCount(1, GenerationTrace::byProject($hash)->get());
    }

    public function test_active_scope_filters_correctly(): void
    {
        GenerationTrace::factory()->create(['is_active' => true]);
        GenerationTrace::factory()->create(['is_active' => false]);

        $this->assertCount(1, GenerationTrace::active()->get());
    }

    public function test_searchable_columns_returns_correct_fields(): void
    {
        $method = new ReflectionMethod(GenerationTrace::class, 'searchableColumns');
        $columns = $method->invoke(new GenerationTrace);

        $this->assertEquals(['entity_name', 'scaffolder_args'], $columns);
    }

    public function test_search_scope_finds_matching_records(): void
    {
        GenerationTrace::factory()->create(['entity_name' => 'UniqueEntityName']);
        GenerationTrace::factory()->create(['entity_name' => 'DifferentEntity']);

        $this->assertCount(1, GenerationTrace::search('UniqueEntityName')->get());
    }

    public function test_owner_can_view_own_trace(): void
    {
        $owner = User::factory()->create();
        $record = GenerationTrace::factory()->create(['owner_id' => $owner->id]);

        $this->assertTrue($owner->can('view', $record));
    }

    public function test_admin_can_manage_any_trace(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $record = GenerationTrace::factory()->create();

        $this->assertTrue($admin->can('view', $record));
        $this->assertTrue($admin->can('update', $record));
        $this->assertTrue($admin->can('delete', $record));
    }

    public function test_creation_is_logged(): void
    {
        $record = GenerationTrace::factory()->create();

        $activity = \Spatie\Activitylog\Models\Activity::where('subject_type', GenerationTrace::class)
            ->where('subject_id', $record->id)
            ->where('event', 'created')
            ->first();

        $this->assertNotNull($activity);
    }

    public function test_entity_events_are_dispatched(): void
    {
        \Illuminate\Support\Facades\Event::fake([\Aicl\Events\EntityCreated::class]);

        GenerationTrace::factory()->create();

        \Illuminate\Support\Facades\Event::assertDispatched(\Aicl\Events\EntityCreated::class);
    }

    public function test_factory_processed_state(): void
    {
        $record = GenerationTrace::factory()->processed()->create();

        $this->assertTrue($record->is_processed);
        $this->assertNotNull($record->structural_score);
    }

    public function test_factory_with_high_score_state(): void
    {
        $record = GenerationTrace::factory()->withHighScore()->create();

        $this->assertGreaterThanOrEqual(95.0, (float) $record->structural_score);
        $this->assertGreaterThanOrEqual(90.0, (float) $record->semantic_score);
        $this->assertEquals(0, $record->fix_iterations);
    }

    public function test_factory_with_fixes_state(): void
    {
        $record = GenerationTrace::factory()->withFixes()->create();

        $this->assertGreaterThanOrEqual(1, $record->fix_iterations);
        $this->assertIsArray($record->fixes_applied);
        $this->assertNotEmpty($record->fixes_applied);
    }

    public function test_file_manifest_casts_to_array(): void
    {
        $manifest = ['app/Models/Test.php', 'database/migrations/create_tests_table.php'];
        $record = GenerationTrace::factory()->create(['file_manifest' => $manifest]);

        $this->assertIsArray($record->file_manifest);
        $this->assertCount(2, $record->file_manifest);
    }

    public function test_casts_are_correct(): void
    {
        $record = GenerationTrace::factory()->create([
            'fix_iterations' => 2,
            'pipeline_duration' => 300,
            'is_processed' => true,
        ]);

        $this->assertIsBool($record->is_processed);
        $this->assertIsBool($record->is_active);
        $this->assertIsInt($record->fix_iterations);
        $this->assertIsInt($record->pipeline_duration);
    }

    public function test_agent_versions_casts_to_array(): void
    {
        $versions = ['architect' => 'claude-opus-4-2026-01-01', 'rlm' => 'claude-opus-4-2026-01-01'];
        $record = GenerationTrace::factory()->create(['agent_versions' => $versions]);

        $this->assertIsArray($record->agent_versions);
        $this->assertArrayHasKey('architect', $record->agent_versions);
    }
}
