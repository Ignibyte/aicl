<?php

namespace Aicl\Tests\Hub;

use Aicl\Enums\ResolutionMethod;
use Aicl\Models\FailureReport;
use Aicl\Models\RlmFailure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class FailureReportTest extends TestCase
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
            'ViewAny:FailureReport', 'View:FailureReport', 'Create:FailureReport',
            'Update:FailureReport', 'Delete:FailureReport',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin->syncPermissions(Permission::where('guard_name', 'web')->get());
    }

    public function test_failure_report_uses_uuid_primary_key(): void
    {
        $record = FailureReport::factory()->create();

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $record->id
        );
    }

    public function test_failure_report_can_be_created(): void
    {
        $owner = User::factory()->create();
        $record = FailureReport::factory()->create(['owner_id' => $owner->id]);

        $this->assertDatabaseHas('failure_reports', ['id' => $record->id]);
    }

    public function test_failure_report_belongs_to_owner(): void
    {
        $owner = User::factory()->create();
        $record = FailureReport::factory()->create(['owner_id' => $owner->id]);

        $this->assertTrue($record->owner->is($owner));
    }

    public function test_failure_report_belongs_to_rlm_failure(): void
    {
        $failure = RlmFailure::factory()->create();
        $record = FailureReport::factory()->create(['rlm_failure_id' => $failure->id]);

        $this->assertTrue($record->failure->is($failure));
    }

    public function test_rlm_failure_alias_returns_same_relationship(): void
    {
        $failure = RlmFailure::factory()->create();
        $record = FailureReport::factory()->create(['rlm_failure_id' => $failure->id]);

        $this->assertTrue($record->rlmFailure->is($record->failure));
    }

    public function test_failure_report_soft_deletes(): void
    {
        $record = FailureReport::factory()->create();
        $record->delete();

        $this->assertSoftDeleted('failure_reports', ['id' => $record->id]);
    }

    public function test_resolution_method_is_cast_to_enum(): void
    {
        $record = FailureReport::factory()->resolved()->create([
            'resolution_method' => ResolutionMethod::ScaffoldingFix,
        ]);

        $this->assertInstanceOf(ResolutionMethod::class, $record->resolution_method);
        $this->assertEquals(ResolutionMethod::ScaffoldingFix, $record->resolution_method);
    }

    public function test_resolution_method_label_and_color(): void
    {
        $this->assertEquals('Scaffolding Fix', ResolutionMethod::ScaffoldingFix->label());
        $this->assertEquals('success', ResolutionMethod::ScaffoldingFix->color());
        $this->assertEquals("Won't Fix", ResolutionMethod::WontFix->label());
        $this->assertEquals('danger', ResolutionMethod::WontFix->color());
    }

    public function test_resolved_scope_filters_resolved_records(): void
    {
        FailureReport::factory()->resolved()->create();
        FailureReport::factory()->unresolved()->create();

        $this->assertCount(1, FailureReport::resolved()->get());
    }

    public function test_unresolved_scope_filters_unresolved_records(): void
    {
        FailureReport::factory()->resolved()->create();
        FailureReport::factory()->unresolved()->create();

        $this->assertCount(1, FailureReport::unresolved()->get());
    }

    public function test_by_project_scope(): void
    {
        $hash = fake()->sha256();
        FailureReport::factory()->create(['project_hash' => $hash]);
        FailureReport::factory()->create(['project_hash' => fake()->sha256()]);

        $this->assertCount(1, FailureReport::byProject($hash)->get());
    }

    public function test_by_phase_scope(): void
    {
        FailureReport::factory()->create(['phase' => 'Phase 3: Generate']);
        FailureReport::factory()->create(['phase' => 'Phase 5: Register']);

        $this->assertCount(1, FailureReport::byPhase('Phase 3: Generate')->get());
    }

    public function test_by_agent_scope(): void
    {
        FailureReport::factory()->create(['agent' => '/architect']);
        FailureReport::factory()->create(['agent' => '/tester']);

        $this->assertCount(1, FailureReport::byAgent('/architect')->get());
    }

    public function test_active_scope_filters_correctly(): void
    {
        FailureReport::factory()->create(['is_active' => true]);
        FailureReport::factory()->create(['is_active' => false]);

        $this->assertCount(1, FailureReport::active()->get());
    }

    public function test_searchable_columns_returns_correct_fields(): void
    {
        $method = new ReflectionMethod(FailureReport::class, 'searchableColumns');
        $columns = $method->invoke(new FailureReport);

        $this->assertEquals(['entity_name', 'project_hash', 'phase', 'agent'], $columns);
    }

    public function test_search_scope_finds_matching_records(): void
    {
        FailureReport::factory()->create(['entity_name' => 'UniqueInvoiceEntity']);
        FailureReport::factory()->create(['entity_name' => 'DifferentProject']);

        $this->assertCount(1, FailureReport::search('UniqueInvoice')->get());
    }

    public function test_owner_can_view_own_failure_report(): void
    {
        $owner = User::factory()->create();
        $record = FailureReport::factory()->create(['owner_id' => $owner->id]);

        $this->assertTrue($owner->can('view', $record));
    }

    public function test_admin_can_manage_any_failure_report(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $record = FailureReport::factory()->create();

        $this->assertTrue($admin->can('view', $record));
        $this->assertTrue($admin->can('update', $record));
        $this->assertTrue($admin->can('delete', $record));
    }

    public function test_failure_report_creation_is_logged(): void
    {
        $record = FailureReport::factory()->create();

        $activity = \Spatie\Activitylog\Models\Activity::where('subject_type', FailureReport::class)
            ->where('subject_id', $record->id)
            ->where('event', 'created')
            ->first();

        $this->assertNotNull($activity);
    }

    public function test_entity_events_are_dispatched(): void
    {
        \Illuminate\Support\Facades\Event::fake([\Aicl\Events\EntityCreated::class]);

        FailureReport::factory()->create();

        \Illuminate\Support\Facades\Event::assertDispatched(\Aicl\Events\EntityCreated::class);
    }

    public function test_factory_resolved_state(): void
    {
        $record = FailureReport::factory()->resolved()->create();

        $this->assertTrue($record->resolved);
        $this->assertNotNull($record->resolution_method);
        $this->assertNotNull($record->time_to_resolve);
        $this->assertNotNull($record->resolved_at);
    }

    public function test_factory_unresolved_state(): void
    {
        $record = FailureReport::factory()->unresolved()->create();

        $this->assertFalse($record->resolved);
        $this->assertNull($record->resolution_method);
        $this->assertNull($record->time_to_resolve);
        $this->assertNull($record->resolved_at);
    }

    public function test_factory_with_scaffolder_args_state(): void
    {
        $record = FailureReport::factory()->withScaffolderArgs()->create();

        $this->assertIsArray($record->scaffolder_args);
        $this->assertArrayHasKey('fields', $record->scaffolder_args);
    }

    public function test_casts_are_correct(): void
    {
        $record = FailureReport::factory()->resolved()->create([
            'scaffolder_args' => ['foo' => 'bar'],
        ]);

        $this->assertIsBool($record->resolved);
        $this->assertIsBool($record->is_active);
        $this->assertIsArray($record->scaffolder_args);
        $this->assertIsInt($record->time_to_resolve);
        $this->assertInstanceOf(\Carbon\Carbon::class, $record->reported_at);
        $this->assertInstanceOf(\Carbon\Carbon::class, $record->resolved_at);
    }
}
