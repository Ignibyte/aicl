<?php

namespace Aicl\Tests\Unit\Policies;

use Aicl\Policies\BasePolicy;
use Aicl\Policies\RolePolicy;
use Aicl\Policies\UserPolicy;
use PHPUnit\Framework\TestCase;

class AppPolicyTest extends TestCase
{
    // ─── UserPolicy ────────────────────────────────────────────

    public function test_user_policy_extends_base_policy(): void
    {
        $this->assertTrue(is_subclass_of(UserPolicy::class, BasePolicy::class));
    }

    public function test_user_policy_permission_prefix(): void
    {
        $reflection = new \ReflectionMethod(UserPolicy::class, 'permissionPrefix');
        $reflection->setAccessible(true);

        $policy = new UserPolicy;
        $this->assertEquals('User', $reflection->invoke($policy));
    }

    public function test_user_policy_overrides_view(): void
    {
        $reflection = new \ReflectionMethod(UserPolicy::class, 'view');
        $this->assertEquals(UserPolicy::class, $reflection->getDeclaringClass()->getName());
    }

    public function test_user_policy_overrides_update(): void
    {
        $reflection = new \ReflectionMethod(UserPolicy::class, 'update');
        $this->assertEquals(UserPolicy::class, $reflection->getDeclaringClass()->getName());
    }

    public function test_user_policy_inherits_view_any(): void
    {
        $reflection = new \ReflectionMethod(UserPolicy::class, 'viewAny');
        $this->assertEquals(BasePolicy::class, $reflection->getDeclaringClass()->getName());
    }

    public function test_user_policy_inherits_create(): void
    {
        $reflection = new \ReflectionMethod(UserPolicy::class, 'create');
        $this->assertEquals(BasePolicy::class, $reflection->getDeclaringClass()->getName());
    }

    public function test_user_policy_inherits_delete(): void
    {
        $reflection = new \ReflectionMethod(UserPolicy::class, 'delete');
        $this->assertEquals(BasePolicy::class, $reflection->getDeclaringClass()->getName());
    }

    // ─── RolePolicy ────────────────────────────────────────────

    public function test_role_policy_exists(): void
    {
        $this->assertTrue(class_exists(RolePolicy::class));
    }

    public function test_role_policy_uses_handles_authorization(): void
    {
        $uses = class_uses(RolePolicy::class);
        $this->assertContains(\Illuminate\Auth\Access\HandlesAuthorization::class, $uses);
    }

    public function test_role_policy_defines_view_any(): void
    {
        $this->assertTrue(method_exists(RolePolicy::class, 'viewAny'));
    }

    public function test_role_policy_defines_view(): void
    {
        $this->assertTrue(method_exists(RolePolicy::class, 'view'));
    }

    public function test_role_policy_defines_create(): void
    {
        $this->assertTrue(method_exists(RolePolicy::class, 'create'));
    }

    public function test_role_policy_defines_update(): void
    {
        $this->assertTrue(method_exists(RolePolicy::class, 'update'));
    }

    public function test_role_policy_defines_delete(): void
    {
        $this->assertTrue(method_exists(RolePolicy::class, 'delete'));
    }

    public function test_role_policy_defines_restore(): void
    {
        $this->assertTrue(method_exists(RolePolicy::class, 'restore'));
    }

    public function test_role_policy_defines_force_delete(): void
    {
        $this->assertTrue(method_exists(RolePolicy::class, 'forceDelete'));
    }

    public function test_role_policy_defines_force_delete_any(): void
    {
        $this->assertTrue(method_exists(RolePolicy::class, 'forceDeleteAny'));
    }

    public function test_role_policy_defines_restore_any(): void
    {
        $this->assertTrue(method_exists(RolePolicy::class, 'restoreAny'));
    }

    public function test_role_policy_defines_replicate(): void
    {
        $this->assertTrue(method_exists(RolePolicy::class, 'replicate'));
    }

    public function test_role_policy_defines_reorder(): void
    {
        $this->assertTrue(method_exists(RolePolicy::class, 'reorder'));
    }
}
