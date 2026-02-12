<?php

// PATTERN: Feature test covers CRUD, authorization, scopes, and relationships.
// PATTERN: Uses RefreshDatabase for clean state.
// PATTERN: Seeds permissions in setUp() since RefreshDatabase wipes them.
// PATTERN: Tests follow naming convention: test_{entity}_{action}

namespace Tests\Feature\Entities;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ProjectTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // PATTERN: Reset permission cache before each test.
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $this->seedPermissions();
    }

    // PATTERN: Seed Shield-format permissions for the entity.
    protected function seedPermissions(): void
    {
        $permissions = [
            'ViewAny:Project', 'View:Project', 'Create:Project',
            'Update:Project', 'Delete:Project',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin->syncPermissions(Permission::where('guard_name', 'web')->get());
    }

    // --- CRUD Tests ---

    public function test_project_can_be_created(): void
    {
        $owner = User::factory()->create();
        $record = Project::factory()->create(['owner_id' => $owner->id]);

        $this->assertDatabaseHas('projects', ['id' => $record->id]);
    }

    public function test_project_belongs_to_owner(): void
    {
        $owner = User::factory()->create();
        $record = Project::factory()->create(['owner_id' => $owner->id]);

        $this->assertTrue($record->owner->is($owner));
    }

    public function test_project_soft_deletes(): void
    {
        $record = Project::factory()->create();
        $record->delete();

        $this->assertSoftDeleted('projects', ['id' => $record->id]);
    }

    // --- Authorization Tests ---

    public function test_owner_can_view_own_project(): void
    {
        $owner = User::factory()->create();
        $record = Project::factory()->create(['owner_id' => $owner->id]);

        $this->assertTrue($owner->can('view', $record));
    }

    public function test_admin_can_manage_any_project(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $record = Project::factory()->create();

        $this->assertTrue($admin->can('view', $record));
        $this->assertTrue($admin->can('update', $record));
        $this->assertTrue($admin->can('delete', $record));
    }

    // --- Audit Trail Tests ---

    public function test_project_creation_is_logged(): void
    {
        $record = Project::factory()->create();

        $activity = \Spatie\Activitylog\Models\Activity::where('subject_type', Project::class)
            ->where('subject_id', $record->id)
            ->where('event', 'created')
            ->first();

        $this->assertNotNull($activity);
    }

    // --- Entity Event Tests ---

    public function test_entity_events_are_dispatched(): void
    {
        \Illuminate\Support\Facades\Event::fake([\Aicl\Events\EntityCreated::class]);

        Project::factory()->create();

        \Illuminate\Support\Facades\Event::assertDispatched(\Aicl\Events\EntityCreated::class);
    }

    // --- Scope Tests ---

    public function test_active_scope_filters_correctly(): void
    {
        Project::factory()->create(['is_active' => true]);
        Project::factory()->create(['is_active' => false]);

        $this->assertCount(1, Project::active()->get());
    }
}
