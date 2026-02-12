<?php

namespace Aicl\Tests\Hub;

use Aicl\Enums\FailureCategory;
use Aicl\Enums\FailureSeverity;
use Aicl\Models\RlmFailure;
use Aicl\States\RlmFailure\Confirmed;
use Aicl\States\RlmFailure\Deprecated;
use Aicl\States\RlmFailure\Investigating;
use Aicl\States\RlmFailure\Reported;
use Aicl\States\RlmFailure\Resolved;
use Aicl\States\RlmFailure\WontFix;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class RlmFailureTest extends TestCase
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
            'ViewAny:RlmFailure', 'View:RlmFailure', 'Create:RlmFailure',
            'Update:RlmFailure', 'Delete:RlmFailure',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin->syncPermissions(Permission::where('guard_name', 'web')->get());
    }

    public function test_rlm_failure_uses_uuid_primary_key(): void
    {
        $record = RlmFailure::factory()->create();

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $record->id
        );
    }

    public function test_rlm_failure_can_be_created(): void
    {
        $owner = User::factory()->create();
        $record = RlmFailure::factory()->create(['owner_id' => $owner->id]);

        $this->assertDatabaseHas('rlm_failures', ['id' => $record->id]);
    }

    public function test_rlm_failure_belongs_to_owner(): void
    {
        $owner = User::factory()->create();
        $record = RlmFailure::factory()->create(['owner_id' => $owner->id]);

        $this->assertTrue($record->owner->is($owner));
    }

    public function test_rlm_failure_soft_deletes(): void
    {
        $record = RlmFailure::factory()->create();
        $record->delete();

        $this->assertSoftDeleted('rlm_failures', ['id' => $record->id]);
    }

    public function test_owner_can_view_own_rlm_failure(): void
    {
        $owner = User::factory()->create();
        $record = RlmFailure::factory()->create(['owner_id' => $owner->id]);

        $this->assertTrue($owner->can('view', $record));
    }

    public function test_admin_can_manage_any_rlm_failure(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $record = RlmFailure::factory()->create();

        $this->assertTrue($admin->can('view', $record));
        $this->assertTrue($admin->can('update', $record));
        $this->assertTrue($admin->can('delete', $record));
    }

    public function test_rlm_failure_has_default_state(): void
    {
        $record = RlmFailure::factory()->create();

        $this->assertInstanceOf(Reported::class, $record->status);
    }

    public function test_rlm_failure_can_transition_reported_to_confirmed(): void
    {
        $record = RlmFailure::factory()->create();

        $record->status->transitionTo(Confirmed::class);
        $record->refresh();

        $this->assertInstanceOf(Confirmed::class, $record->status);
    }

    public function test_rlm_failure_can_transition_confirmed_to_investigating(): void
    {
        $record = RlmFailure::factory()->confirmed()->create();

        $record->status->transitionTo(Investigating::class);
        $record->refresh();

        $this->assertInstanceOf(Investigating::class, $record->status);
    }

    public function test_rlm_failure_can_transition_investigating_to_resolved(): void
    {
        $record = RlmFailure::factory()->investigating()->create();

        $record->status->transitionTo(Resolved::class);
        $record->refresh();

        $this->assertInstanceOf(Resolved::class, $record->status);
    }

    public function test_rlm_failure_can_transition_investigating_to_wont_fix(): void
    {
        $record = RlmFailure::factory()->investigating()->create();

        $record->status->transitionTo(WontFix::class);
        $record->refresh();

        $this->assertInstanceOf(WontFix::class, $record->status);
    }

    public function test_rlm_failure_can_transition_reported_to_deprecated(): void
    {
        $record = RlmFailure::factory()->create();

        $record->status->transitionTo(Deprecated::class);
        $record->refresh();

        $this->assertInstanceOf(Deprecated::class, $record->status);
    }

    public function test_category_casts_to_enum(): void
    {
        $record = RlmFailure::factory()->create(['category' => 'scaffolding']);

        $this->assertInstanceOf(FailureCategory::class, $record->category);
        $this->assertEquals(FailureCategory::Scaffolding, $record->category);
    }

    public function test_severity_casts_to_enum(): void
    {
        $record = RlmFailure::factory()->create(['severity' => 'critical']);

        $this->assertInstanceOf(FailureSeverity::class, $record->severity);
        $this->assertEquals(FailureSeverity::Critical, $record->severity);
    }

    public function test_computed_resolution_rate(): void
    {
        $record = RlmFailure::factory()->create([
            'report_count' => 10,
            'resolution_count' => 7,
        ]);

        $this->assertEquals(0.7, $record->computed_resolution_rate);
    }

    public function test_computed_resolution_rate_zero_when_no_reports(): void
    {
        $record = RlmFailure::factory()->create([
            'report_count' => 0,
            'resolution_count' => 0,
        ]);

        $this->assertEquals(0.0, $record->computed_resolution_rate);
    }

    public function test_scope_promotable_filters_correctly(): void
    {
        RlmFailure::factory()->create([
            'report_count' => 5,
            'project_count' => 3,
            'promoted_to_base' => false,
        ]);
        RlmFailure::factory()->create([
            'report_count' => 1,
            'project_count' => 1,
            'promoted_to_base' => false,
        ]);
        RlmFailure::factory()->create([
            'report_count' => 10,
            'project_count' => 5,
            'promoted_to_base' => true,
        ]);

        $promotable = RlmFailure::promotable()->get();
        $this->assertCount(1, $promotable);
    }

    public function test_scope_by_entity_context(): void
    {
        RlmFailure::factory()->create([
            'entity_context' => ['entity' => 'User', 'phase' => 'generate'],
        ]);
        RlmFailure::factory()->create([
            'entity_context' => ['entity' => 'Task', 'phase' => 'validate'],
        ]);

        $results = RlmFailure::byEntityContext(['entity' => 'User'])->get();
        $this->assertCount(1, $results);
    }

    public function test_searchable_columns(): void
    {
        $expected = ['title', 'description', 'failure_code', 'category'];
        $method = new ReflectionMethod(RlmFailure::class, 'searchableColumns');

        $this->assertEquals($expected, $method->invoke(new RlmFailure));
    }

    public function test_search_scope_finds_matching_records(): void
    {
        RlmFailure::factory()->create(['title' => 'Missing observer registration']);
        RlmFailure::factory()->create(['title' => 'Wrong enum cases']);

        $this->assertCount(1, RlmFailure::search('observer')->get());
    }

    public function test_rlm_failure_creation_is_logged(): void
    {
        $record = RlmFailure::factory()->create();

        $activity = \Spatie\Activitylog\Models\Activity::where('subject_type', RlmFailure::class)
            ->where('subject_id', $record->id)
            ->where('event', 'created')
            ->first();

        $this->assertNotNull($activity);
    }

    public function test_entity_events_are_dispatched(): void
    {
        \Illuminate\Support\Facades\Event::fake([\Aicl\Events\EntityCreated::class]);

        RlmFailure::factory()->create();

        \Illuminate\Support\Facades\Event::assertDispatched(\Aicl\Events\EntityCreated::class);
    }

    public function test_active_scope_filters_correctly(): void
    {
        RlmFailure::factory()->create(['is_active' => true]);
        RlmFailure::factory()->create(['is_active' => false]);

        $this->assertCount(1, RlmFailure::active()->get());
    }

    public function test_factory_promotable_state(): void
    {
        $record = RlmFailure::factory()->promotable()->create();

        $this->assertGreaterThanOrEqual(3, $record->report_count);
        $this->assertGreaterThanOrEqual(2, $record->project_count);
        $this->assertFalse($record->promoted_to_base);
    }

    public function test_factory_promoted_state(): void
    {
        $record = RlmFailure::factory()->promoted()->create();

        $this->assertTrue($record->promoted_to_base);
        $this->assertNotNull($record->promoted_at);
    }

    public function test_factory_scaffolding_fixed_state(): void
    {
        $record = RlmFailure::factory()->scaffoldingFixed()->create();

        $this->assertTrue($record->scaffolding_fixed);
    }

    public function test_entity_context_casts_to_array(): void
    {
        $context = ['entity' => 'Project', 'phase' => 'generate', 'file' => 'Model.php'];
        $record = RlmFailure::factory()->create(['entity_context' => $context]);
        $record->refresh();

        $this->assertIsArray($record->entity_context);
        $this->assertEquals('Project', $record->entity_context['entity']);
    }
}
