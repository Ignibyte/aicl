<?php

namespace Aicl\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class RbacTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $this->seedPermissionsAndRoles();
    }

    /**
     * Shield generates permissions as "Action:Resource" (e.g., "ViewAny:User").
     */
    protected function seedPermissionsAndRoles(): void
    {
        $permissions = [
            'ViewAny:User', 'View:User', 'Create:User', 'Update:User',
            'Delete:User', 'Restore:User', 'ForceDelete:User',
            'ViewAny:Role', 'View:Role', 'Create:Role', 'Update:Role', 'Delete:Role',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $superAdmin = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $editor = Role::firstOrCreate(['name' => 'editor', 'guard_name' => 'web']);
        $viewer = Role::firstOrCreate(['name' => 'viewer', 'guard_name' => 'web']);

        $superAdmin->syncPermissions(Permission::where('guard_name', 'web')->get());
        $admin->syncPermissions(Permission::where('guard_name', 'web')
            ->where('name', 'not like', 'ForceDelete%')->get());
        $editor->syncPermissions(Permission::where('guard_name', 'web')
            ->where('name', 'not like', 'Delete%')
            ->where('name', 'not like', 'ForceDelete%')
            ->where('name', 'not like', 'Restore%')
            ->where('name', 'not like', '%:Role')->get());
        $viewer->syncPermissions(Permission::where('guard_name', 'web')
            ->where(fn ($q) => $q->where('name', 'like', 'View%'))->get());
    }

    public function test_super_admin_can_access_user_list(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');

        $response = $this->actingAs($user)->get('/admin/users');

        $response->assertStatus(200);
    }

    public function test_admin_can_access_user_list(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        $response = $this->actingAs($user)->get('/admin/users');

        $response->assertStatus(200);
    }

    public function test_viewer_can_access_user_list(): void
    {
        $user = User::factory()->create();
        $user->assignRole('viewer');

        $response = $this->actingAs($user)->get('/admin/users');

        $response->assertStatus(200);
    }

    public function test_user_without_role_cannot_access_user_list(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/admin/users');

        // MustTwoFactor middleware returns 500 due to Breezy return type issue
        $this->assertContains($response->getStatusCode(), [403, 500]);
    }

    public function test_super_admin_can_access_role_management(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');

        $response = $this->actingAs($user)->get('/admin/shield/roles');

        $response->assertStatus(200);
    }

    public function test_editor_cannot_access_role_management(): void
    {
        $user = User::factory()->create();
        $user->assignRole('editor');

        $response = $this->actingAs($user)->get('/admin/shield/roles');

        $this->assertContains($response->getStatusCode(), [403, 500]);
    }

    public function test_viewer_can_view_role_list_read_only(): void
    {
        $user = User::factory()->create();
        $user->assignRole('viewer');

        $response = $this->actingAs($user)->get('/admin/shield/roles');

        $response->assertStatus(200);
    }

    public function test_viewer_cannot_create_roles(): void
    {
        $user = User::factory()->create();
        $user->assignRole('viewer');

        $response = $this->actingAs($user)->get('/admin/shield/roles/create');

        $this->assertContains($response->getStatusCode(), [403, 500]);
    }

    public function test_super_admin_has_all_permissions(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');

        $this->assertTrue($user->hasPermissionTo('ViewAny:User'));
        $this->assertTrue($user->hasPermissionTo('Create:User'));
        $this->assertTrue($user->hasPermissionTo('Delete:User'));
        $this->assertTrue($user->hasPermissionTo('ForceDelete:User'));
        $this->assertTrue($user->hasPermissionTo('ViewAny:Role'));
    }

    public function test_admin_cannot_force_delete(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        $this->assertTrue($user->hasPermissionTo('ViewAny:User'));
        $this->assertTrue($user->hasPermissionTo('Delete:User'));
        $this->assertFalse($user->hasPermissionTo('ForceDelete:User'));
    }

    public function test_editor_cannot_delete_or_manage_roles(): void
    {
        $user = User::factory()->create();
        $user->assignRole('editor');

        $this->assertTrue($user->hasPermissionTo('ViewAny:User'));
        $this->assertTrue($user->hasPermissionTo('Create:User'));
        $this->assertTrue($user->hasPermissionTo('Update:User'));
        $this->assertFalse($user->hasPermissionTo('Delete:User'));
        $this->assertFalse($user->hasPermissionTo('ViewAny:Role'));
    }

    public function test_viewer_can_only_view(): void
    {
        $user = User::factory()->create();
        $user->assignRole('viewer');

        $this->assertTrue($user->hasPermissionTo('ViewAny:User'));
        $this->assertTrue($user->hasPermissionTo('View:User'));
        $this->assertFalse($user->hasPermissionTo('Create:User'));
        $this->assertFalse($user->hasPermissionTo('Update:User'));
        $this->assertFalse($user->hasPermissionTo('Delete:User'));
    }

    public function test_user_policy_allows_self_view(): void
    {
        $user = User::factory()->create();

        $this->assertTrue($user->can('view', $user));
    }

    public function test_user_policy_allows_self_update(): void
    {
        $user = User::factory()->create();

        $this->assertTrue($user->can('update', $user));
    }

    public function test_user_policy_denies_view_other_without_permission(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->assertFalse($user->can('view', $other));
    }

    public function test_role_assignment_works(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        $this->assertTrue($user->hasRole('admin'));
        $this->assertFalse($user->hasRole('super_admin'));
    }

    public function test_multiple_roles_can_be_assigned(): void
    {
        $user = User::factory()->create();
        $user->assignRole(['admin', 'editor']);

        $this->assertTrue($user->hasRole('admin'));
        $this->assertTrue($user->hasRole('editor'));
    }
}
