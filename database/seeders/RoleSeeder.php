<?php

declare(strict_types=1);

namespace Aicl\Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    /**
     * Seed the default AICL roles and assign permissions.
     *
     * Roles hierarchy:
     *   super_admin — full access (bypasses all gates via Shield)
     *   admin       — all resource CRUD + user management
     *   editor      — create/update/view resources, no delete or user management
     *   viewer      — read-only access to resources
     */
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $superAdmin = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $editor = Role::firstOrCreate(['name' => 'editor', 'guard_name' => 'web']);
        $viewer = Role::firstOrCreate(['name' => 'viewer', 'guard_name' => 'web']);

        $allPermissions = Permission::where('guard_name', 'web')->get();

        $superAdmin->syncPermissions($allPermissions);

        $admin->syncPermissions(
            $allPermissions->filter(fn (Permission $p) => ! str_contains($p->name, 'ForceDelete'))
        );

        $editor->syncPermissions(
            $allPermissions->filter(function (Permission $p) {
                $name = $p->name;

                if (str_contains($name, 'Delete') || str_contains($name, 'Restore')) {
                    return false;
                }

                if (str_contains($name, 'Role')) {
                    return false;
                }

                return true;
            })
        );

        $viewer->syncPermissions(
            $allPermissions->filter(function (Permission $p) {
                $name = $p->name;

                return str_contains($name, 'ViewAny') || str_contains($name, 'View') || str_starts_with($name, 'view_');
            })
        );
    }
}
