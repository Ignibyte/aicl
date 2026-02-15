<?php

namespace Aicl\Tests\Unit\Policies;

use Aicl\Events\EntityCreated;
use Aicl\Events\EntityDeleted;
use Aicl\Events\EntityUpdated;
use Aicl\Models\FailureReport;
use Aicl\Models\GenerationTrace;
use Aicl\Models\PreventionRule;
use Aicl\Models\RlmFailure;
use Aicl\Models\RlmLesson;
use Aicl\Models\RlmPattern;
use Aicl\Models\RlmScore;
use Aicl\Policies\BasePolicy;
use Aicl\Policies\FailureReportPolicy;
use Aicl\Policies\GenerationTracePolicy;
use Aicl\Policies\PreventionRulePolicy;
use Aicl\Policies\RlmFailurePolicy;
use Aicl\Policies\RlmLessonPolicy;
use Aicl\Policies\RlmPatternPolicy;
use Aicl\Policies\RlmScorePolicy;
use Aicl\Policies\RolePolicy;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Comprehensive coverage for ALL AICL policies:
 *
 * - RolePolicy (standalone, not extending BasePolicy)
 * - 7 hub entity policies (extending BasePolicy with owner checks)
 * - Super admin bypass via Shield's Gate::before
 * - All BasePolicy methods: restore, forceDelete, restoreAny, forceDeleteAny, replicate, reorder
 * - Permission prefix correctness
 * - Structural assertions (extends, overrides, inheritance)
 */
class PolicyCoverageTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected User $otherUser;

    protected User $superAdmin;

    /** @var list<string> */
    private static array $hubEntityNames = [
        'RlmPattern',
        'RlmFailure',
        'RlmLesson',
        'FailureReport',
        'GenerationTrace',
        'PreventionRule',
        'RlmScore',
    ];

    /** @var list<string> */
    private static array $allActions = [
        'ViewAny', 'View', 'Create', 'Update', 'Delete',
        'Restore', 'ForceDelete', 'RestoreAny', 'ForceDeleteAny',
        'Replicate', 'Reorder',
    ];

    /** @var list<string> */
    private static array $roleActions = [
        'ViewAny', 'View', 'Create', 'Update', 'Delete',
        'Restore', 'ForceDelete', 'RestoreAny', 'ForceDeleteAny',
        'Replicate', 'Reorder',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake([
            EntityCreated::class,
            EntityUpdated::class,
            EntityDeleted::class,
        ]);

        $this->owner = User::factory()->create();
        $this->otherUser = User::factory()->create();
        $this->superAdmin = User::factory()->create();

        // Create all permissions for hub entities (all actions)
        foreach (self::$hubEntityNames as $entity) {
            foreach (self::$allActions as $action) {
                Permission::firstOrCreate([
                    'name' => "{$action}:{$entity}",
                    'guard_name' => 'web',
                ]);
            }
        }

        // Create all permissions for Role
        foreach (self::$roleActions as $action) {
            Permission::firstOrCreate([
                'name' => "{$action}:Role",
                'guard_name' => 'web',
            ]);
        }

        // Seed the super_admin role and assign all permissions
        $this->artisan('db:seed', [
            '--class' => 'Aicl\Database\Seeders\RoleSeeder',
            '--no-interaction' => true,
        ]);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Assign super_admin role
        $this->superAdmin->assignRole('super_admin');

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    // ─── Data Providers ─────────────────────────────────────────

    /**
     * @return array<string, array{0: class-string, 1: class-string<Model>, 2: string}>
     */
    public static function hubPolicyProvider(): array
    {
        return [
            'RlmPattern' => [RlmPatternPolicy::class, RlmPattern::class, 'RlmPattern'],
            'RlmFailure' => [RlmFailurePolicy::class, RlmFailure::class, 'RlmFailure'],
            'RlmLesson' => [RlmLessonPolicy::class, RlmLesson::class, 'RlmLesson'],
            'FailureReport' => [FailureReportPolicy::class, FailureReport::class, 'FailureReport'],
            'GenerationTrace' => [GenerationTracePolicy::class, GenerationTrace::class, 'GenerationTrace'],
            'PreventionRule' => [PreventionRulePolicy::class, PreventionRule::class, 'PreventionRule'],
            'RlmScore' => [RlmScorePolicy::class, RlmScore::class, 'RlmScore'],
        ];
    }

    /**
     * @return array<string, array{0: class-string, 1: string}>
     */
    public static function hubPolicyPrefixProvider(): array
    {
        return [
            'RlmPatternPolicy' => [RlmPatternPolicy::class, 'RlmPattern'],
            'RlmFailurePolicy' => [RlmFailurePolicy::class, 'RlmFailure'],
            'RlmLessonPolicy' => [RlmLessonPolicy::class, 'RlmLesson'],
            'FailureReportPolicy' => [FailureReportPolicy::class, 'FailureReport'],
            'GenerationTracePolicy' => [GenerationTracePolicy::class, 'GenerationTrace'],
            'PreventionRulePolicy' => [PreventionRulePolicy::class, 'PreventionRule'],
            'RlmScorePolicy' => [RlmScorePolicy::class, 'RlmScore'],
        ];
    }

    /**
     * Create a model record owned by the given user, handling nested factory relationships.
     *
     * @param  class-string<Model>  $modelClass
     */
    private function createRecord(string $modelClass, User $recordOwner): Model
    {
        $attributes = ['owner_id' => $recordOwner->id];

        // FailureReport belongs to RlmFailure — pre-create to avoid nested User factory
        if ($modelClass === FailureReport::class) {
            $failure = RlmFailure::factory()->create(['owner_id' => $this->owner->id]);
            $attributes['rlm_failure_id'] = $failure->id;
        }

        // PreventionRule optionally belongs to RlmFailure — set null to avoid nested creation
        if ($modelClass === PreventionRule::class) {
            $attributes['rlm_failure_id'] = null;
        }

        return $modelClass::factory()->create($attributes);
    }

    // ═══════════════════════════════════════════════════════════
    // SECTION 1: Structural Assertions — Hub Entity Policies
    // ═══════════════════════════════════════════════════════════

    #[DataProvider('hubPolicyPrefixProvider')]
    public function test_hub_policy_extends_base_policy(string $policyClass, string $expectedPrefix): void
    {
        $this->assertTrue(
            is_subclass_of($policyClass, BasePolicy::class),
            "{$policyClass} should extend BasePolicy"
        );
    }

    #[DataProvider('hubPolicyPrefixProvider')]
    public function test_hub_policy_permission_prefix_is_correct(string $policyClass, string $expectedPrefix): void
    {
        $reflection = new \ReflectionMethod($policyClass, 'permissionPrefix');
        $reflection->setAccessible(true);

        $policy = new $policyClass;
        $this->assertEquals($expectedPrefix, $reflection->invoke($policy));
    }

    #[DataProvider('hubPolicyPrefixProvider')]
    public function test_hub_policy_overrides_view(string $policyClass, string $expectedPrefix): void
    {
        $reflection = new \ReflectionMethod($policyClass, 'view');
        $this->assertEquals(
            $policyClass,
            $reflection->getDeclaringClass()->getName(),
            "{$policyClass} should override view() for owner check"
        );
    }

    #[DataProvider('hubPolicyPrefixProvider')]
    public function test_hub_policy_overrides_update(string $policyClass, string $expectedPrefix): void
    {
        $reflection = new \ReflectionMethod($policyClass, 'update');
        $this->assertEquals(
            $policyClass,
            $reflection->getDeclaringClass()->getName(),
            "{$policyClass} should override update() for owner check"
        );
    }

    #[DataProvider('hubPolicyPrefixProvider')]
    public function test_hub_policy_overrides_delete(string $policyClass, string $expectedPrefix): void
    {
        $reflection = new \ReflectionMethod($policyClass, 'delete');
        $this->assertEquals(
            $policyClass,
            $reflection->getDeclaringClass()->getName(),
            "{$policyClass} should override delete() for owner check"
        );
    }

    #[DataProvider('hubPolicyPrefixProvider')]
    public function test_hub_policy_inherits_view_any(string $policyClass, string $expectedPrefix): void
    {
        $reflection = new \ReflectionMethod($policyClass, 'viewAny');
        $this->assertEquals(
            BasePolicy::class,
            $reflection->getDeclaringClass()->getName(),
            "{$policyClass} should inherit viewAny() from BasePolicy"
        );
    }

    #[DataProvider('hubPolicyPrefixProvider')]
    public function test_hub_policy_inherits_create(string $policyClass, string $expectedPrefix): void
    {
        $reflection = new \ReflectionMethod($policyClass, 'create');
        $this->assertEquals(
            BasePolicy::class,
            $reflection->getDeclaringClass()->getName(),
            "{$policyClass} should inherit create() from BasePolicy"
        );
    }

    #[DataProvider('hubPolicyPrefixProvider')]
    public function test_hub_policy_inherits_restore(string $policyClass, string $expectedPrefix): void
    {
        $reflection = new \ReflectionMethod($policyClass, 'restore');
        $this->assertEquals(
            BasePolicy::class,
            $reflection->getDeclaringClass()->getName(),
            "{$policyClass} should inherit restore() from BasePolicy"
        );
    }

    #[DataProvider('hubPolicyPrefixProvider')]
    public function test_hub_policy_inherits_force_delete(string $policyClass, string $expectedPrefix): void
    {
        $reflection = new \ReflectionMethod($policyClass, 'forceDelete');
        $this->assertEquals(
            BasePolicy::class,
            $reflection->getDeclaringClass()->getName(),
            "{$policyClass} should inherit forceDelete() from BasePolicy"
        );
    }

    // ═══════════════════════════════════════════════════════════
    // SECTION 2: Owner-Based Access — Hub Entity Policies
    // ═══════════════════════════════════════════════════════════

    #[DataProvider('hubPolicyProvider')]
    public function test_owner_can_view_own_record(string $policyClass, string $modelClass, string $entityName): void
    {
        $policy = new $policyClass;
        $record = $this->createRecord($modelClass, $this->owner);

        $this->assertTrue($policy->view($this->owner, $record));
    }

    #[DataProvider('hubPolicyProvider')]
    public function test_owner_can_update_own_record(string $policyClass, string $modelClass, string $entityName): void
    {
        $policy = new $policyClass;
        $record = $this->createRecord($modelClass, $this->owner);

        $this->assertTrue($policy->update($this->owner, $record));
    }

    #[DataProvider('hubPolicyProvider')]
    public function test_owner_can_delete_own_record(string $policyClass, string $modelClass, string $entityName): void
    {
        $policy = new $policyClass;
        $record = $this->createRecord($modelClass, $this->owner);

        $this->assertTrue($policy->delete($this->owner, $record));
    }

    // ═══════════════════════════════════════════════════════════
    // SECTION 3: Non-Owner Without Permission — Denied Access
    // ═══════════════════════════════════════════════════════════

    #[DataProvider('hubPolicyProvider')]
    public function test_non_owner_without_permission_cannot_view(string $policyClass, string $modelClass, string $entityName): void
    {
        $policy = new $policyClass;
        $record = $this->createRecord($modelClass, $this->owner);

        $this->assertFalse($policy->view($this->otherUser, $record));
    }

    #[DataProvider('hubPolicyProvider')]
    public function test_non_owner_without_permission_cannot_update(string $policyClass, string $modelClass, string $entityName): void
    {
        $policy = new $policyClass;
        $record = $this->createRecord($modelClass, $this->owner);

        $this->assertFalse($policy->update($this->otherUser, $record));
    }

    #[DataProvider('hubPolicyProvider')]
    public function test_non_owner_without_permission_cannot_delete(string $policyClass, string $modelClass, string $entityName): void
    {
        $policy = new $policyClass;
        $record = $this->createRecord($modelClass, $this->owner);

        $this->assertFalse($policy->delete($this->otherUser, $record));
    }

    #[DataProvider('hubPolicyProvider')]
    public function test_non_owner_without_permission_cannot_view_any(string $policyClass, string $modelClass, string $entityName): void
    {
        $policy = new $policyClass;

        $this->assertFalse($policy->viewAny($this->otherUser));
    }

    #[DataProvider('hubPolicyProvider')]
    public function test_non_owner_without_permission_cannot_create(string $policyClass, string $modelClass, string $entityName): void
    {
        $policy = new $policyClass;

        $this->assertFalse($policy->create($this->otherUser));
    }

    // ═══════════════════════════════════════════════════════════
    // SECTION 4: Permission-Based Access (Non-Owner With Permission)
    // ═══════════════════════════════════════════════════════════

    #[DataProvider('hubPolicyProvider')]
    public function test_user_with_permission_can_view_any(string $policyClass, string $modelClass, string $entityName): void
    {
        $this->otherUser->givePermissionTo("ViewAny:{$entityName}");
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $policy = new $policyClass;

        $this->assertTrue($policy->viewAny($this->otherUser));
    }

    #[DataProvider('hubPolicyProvider')]
    public function test_user_with_permission_can_view_non_owned_record(string $policyClass, string $modelClass, string $entityName): void
    {
        $this->otherUser->givePermissionTo("View:{$entityName}");
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $policy = new $policyClass;
        $record = $this->createRecord($modelClass, $this->owner);

        $this->assertTrue($policy->view($this->otherUser, $record));
    }

    #[DataProvider('hubPolicyProvider')]
    public function test_user_with_permission_can_create(string $policyClass, string $modelClass, string $entityName): void
    {
        $this->otherUser->givePermissionTo("Create:{$entityName}");
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $policy = new $policyClass;

        $this->assertTrue($policy->create($this->otherUser));
    }

    #[DataProvider('hubPolicyProvider')]
    public function test_user_with_permission_can_update_non_owned_record(string $policyClass, string $modelClass, string $entityName): void
    {
        $this->otherUser->givePermissionTo("Update:{$entityName}");
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $policy = new $policyClass;
        $record = $this->createRecord($modelClass, $this->owner);

        $this->assertTrue($policy->update($this->otherUser, $record));
    }

    #[DataProvider('hubPolicyProvider')]
    public function test_user_with_permission_can_delete_non_owned_record(string $policyClass, string $modelClass, string $entityName): void
    {
        $this->otherUser->givePermissionTo("Delete:{$entityName}");
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $policy = new $policyClass;
        $record = $this->createRecord($modelClass, $this->owner);

        $this->assertTrue($policy->delete($this->otherUser, $record));
    }

    // ═══════════════════════════════════════════════════════════
    // SECTION 5: Extended BasePolicy Methods (restore, forceDelete, etc.)
    // ═══════════════════════════════════════════════════════════

    #[DataProvider('hubPolicyProvider')]
    public function test_user_without_permission_cannot_restore(string $policyClass, string $modelClass, string $entityName): void
    {
        $policy = new $policyClass;
        $record = $this->createRecord($modelClass, $this->owner);

        $this->assertFalse($policy->restore($this->otherUser, $record));
    }

    #[DataProvider('hubPolicyProvider')]
    public function test_user_with_permission_can_restore(string $policyClass, string $modelClass, string $entityName): void
    {
        $this->otherUser->givePermissionTo("Restore:{$entityName}");
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $policy = new $policyClass;
        $record = $this->createRecord($modelClass, $this->owner);

        $this->assertTrue($policy->restore($this->otherUser, $record));
    }

    #[DataProvider('hubPolicyProvider')]
    public function test_user_without_permission_cannot_force_delete(string $policyClass, string $modelClass, string $entityName): void
    {
        $policy = new $policyClass;
        $record = $this->createRecord($modelClass, $this->owner);

        $this->assertFalse($policy->forceDelete($this->otherUser, $record));
    }

    #[DataProvider('hubPolicyProvider')]
    public function test_user_with_permission_can_force_delete(string $policyClass, string $modelClass, string $entityName): void
    {
        $this->otherUser->givePermissionTo("ForceDelete:{$entityName}");
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $policy = new $policyClass;
        $record = $this->createRecord($modelClass, $this->owner);

        $this->assertTrue($policy->forceDelete($this->otherUser, $record));
    }

    #[DataProvider('hubPolicyProvider')]
    public function test_user_without_permission_cannot_restore_any(string $policyClass, string $modelClass, string $entityName): void
    {
        $policy = new $policyClass;

        $this->assertFalse($policy->restoreAny($this->otherUser));
    }

    #[DataProvider('hubPolicyProvider')]
    public function test_user_with_permission_can_restore_any(string $policyClass, string $modelClass, string $entityName): void
    {
        $this->otherUser->givePermissionTo("RestoreAny:{$entityName}");
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $policy = new $policyClass;

        $this->assertTrue($policy->restoreAny($this->otherUser));
    }

    #[DataProvider('hubPolicyProvider')]
    public function test_user_without_permission_cannot_force_delete_any(string $policyClass, string $modelClass, string $entityName): void
    {
        $policy = new $policyClass;

        $this->assertFalse($policy->forceDeleteAny($this->otherUser));
    }

    #[DataProvider('hubPolicyProvider')]
    public function test_user_with_permission_can_force_delete_any(string $policyClass, string $modelClass, string $entityName): void
    {
        $this->otherUser->givePermissionTo("ForceDeleteAny:{$entityName}");
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $policy = new $policyClass;

        $this->assertTrue($policy->forceDeleteAny($this->otherUser));
    }

    #[DataProvider('hubPolicyProvider')]
    public function test_user_without_permission_cannot_replicate(string $policyClass, string $modelClass, string $entityName): void
    {
        $policy = new $policyClass;
        $record = $this->createRecord($modelClass, $this->owner);

        $this->assertFalse($policy->replicate($this->otherUser, $record));
    }

    #[DataProvider('hubPolicyProvider')]
    public function test_user_with_permission_can_replicate(string $policyClass, string $modelClass, string $entityName): void
    {
        $this->otherUser->givePermissionTo("Replicate:{$entityName}");
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $policy = new $policyClass;
        $record = $this->createRecord($modelClass, $this->owner);

        $this->assertTrue($policy->replicate($this->otherUser, $record));
    }

    #[DataProvider('hubPolicyProvider')]
    public function test_user_without_permission_cannot_reorder(string $policyClass, string $modelClass, string $entityName): void
    {
        $policy = new $policyClass;

        $this->assertFalse($policy->reorder($this->otherUser));
    }

    #[DataProvider('hubPolicyProvider')]
    public function test_user_with_permission_can_reorder(string $policyClass, string $modelClass, string $entityName): void
    {
        $this->otherUser->givePermissionTo("Reorder:{$entityName}");
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $policy = new $policyClass;

        $this->assertTrue($policy->reorder($this->otherUser));
    }

    // ═══════════════════════════════════════════════════════════
    // SECTION 6: Super Admin Bypass
    // ═══════════════════════════════════════════════════════════

    #[DataProvider('hubPolicyProvider')]
    public function test_super_admin_can_view_any(string $policyClass, string $modelClass, string $entityName): void
    {
        $policy = new $policyClass;

        $this->assertTrue($policy->viewAny($this->superAdmin));
    }

    #[DataProvider('hubPolicyProvider')]
    public function test_super_admin_can_view_non_owned_record(string $policyClass, string $modelClass, string $entityName): void
    {
        $policy = new $policyClass;
        $record = $this->createRecord($modelClass, $this->owner);

        $this->assertTrue($policy->view($this->superAdmin, $record));
    }

    #[DataProvider('hubPolicyProvider')]
    public function test_super_admin_can_create(string $policyClass, string $modelClass, string $entityName): void
    {
        $policy = new $policyClass;

        $this->assertTrue($policy->create($this->superAdmin));
    }

    #[DataProvider('hubPolicyProvider')]
    public function test_super_admin_can_update_non_owned_record(string $policyClass, string $modelClass, string $entityName): void
    {
        $policy = new $policyClass;
        $record = $this->createRecord($modelClass, $this->owner);

        $this->assertTrue($policy->update($this->superAdmin, $record));
    }

    #[DataProvider('hubPolicyProvider')]
    public function test_super_admin_can_delete_non_owned_record(string $policyClass, string $modelClass, string $entityName): void
    {
        $policy = new $policyClass;
        $record = $this->createRecord($modelClass, $this->owner);

        $this->assertTrue($policy->delete($this->superAdmin, $record));
    }

    #[DataProvider('hubPolicyProvider')]
    public function test_super_admin_can_restore(string $policyClass, string $modelClass, string $entityName): void
    {
        $policy = new $policyClass;
        $record = $this->createRecord($modelClass, $this->owner);

        $this->assertTrue($policy->restore($this->superAdmin, $record));
    }

    #[DataProvider('hubPolicyProvider')]
    public function test_super_admin_can_force_delete(string $policyClass, string $modelClass, string $entityName): void
    {
        $policy = new $policyClass;
        $record = $this->createRecord($modelClass, $this->owner);

        $this->assertTrue($policy->forceDelete($this->superAdmin, $record));
    }

    #[DataProvider('hubPolicyProvider')]
    public function test_super_admin_can_replicate(string $policyClass, string $modelClass, string $entityName): void
    {
        $policy = new $policyClass;
        $record = $this->createRecord($modelClass, $this->owner);

        $this->assertTrue($policy->replicate($this->superAdmin, $record));
    }

    #[DataProvider('hubPolicyProvider')]
    public function test_super_admin_can_reorder(string $policyClass, string $modelClass, string $entityName): void
    {
        $policy = new $policyClass;

        $this->assertTrue($policy->reorder($this->superAdmin));
    }

    // ═══════════════════════════════════════════════════════════
    // SECTION 7: RolePolicy — Standalone (Not Extending BasePolicy)
    // ═══════════════════════════════════════════════════════════

    public function test_role_policy_does_not_extend_base_policy(): void
    {
        $this->assertFalse(
            is_subclass_of(RolePolicy::class, BasePolicy::class),
            'RolePolicy should NOT extend BasePolicy (standalone)'
        );
    }

    public function test_role_policy_uses_handles_authorization(): void
    {
        $traits = class_uses(RolePolicy::class);

        $this->assertContains(
            \Illuminate\Auth\Access\HandlesAuthorization::class,
            $traits
        );
    }

    public function test_role_policy_view_any_requires_permission(): void
    {
        $policy = new RolePolicy;

        $this->assertFalse($policy->viewAny($this->otherUser));
    }

    public function test_role_policy_view_any_granted_with_permission(): void
    {
        $this->otherUser->givePermissionTo('ViewAny:Role');
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $policy = new RolePolicy;

        $this->assertTrue($policy->viewAny($this->otherUser));
    }

    public function test_role_policy_view_requires_permission(): void
    {
        $role = Role::firstOrCreate(['name' => 'test_role', 'guard_name' => 'web']);
        $policy = new RolePolicy;

        $this->assertFalse($policy->view($this->otherUser, $role));
    }

    public function test_role_policy_view_granted_with_permission(): void
    {
        $this->otherUser->givePermissionTo('View:Role');
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $role = Role::firstOrCreate(['name' => 'test_role', 'guard_name' => 'web']);
        $policy = new RolePolicy;

        $this->assertTrue($policy->view($this->otherUser, $role));
    }

    public function test_role_policy_create_requires_permission(): void
    {
        $policy = new RolePolicy;

        $this->assertFalse($policy->create($this->otherUser));
    }

    public function test_role_policy_create_granted_with_permission(): void
    {
        $this->otherUser->givePermissionTo('Create:Role');
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $policy = new RolePolicy;

        $this->assertTrue($policy->create($this->otherUser));
    }

    public function test_role_policy_update_requires_permission(): void
    {
        $role = Role::firstOrCreate(['name' => 'test_role', 'guard_name' => 'web']);
        $policy = new RolePolicy;

        $this->assertFalse($policy->update($this->otherUser, $role));
    }

    public function test_role_policy_update_granted_with_permission(): void
    {
        $this->otherUser->givePermissionTo('Update:Role');
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $role = Role::firstOrCreate(['name' => 'test_role', 'guard_name' => 'web']);
        $policy = new RolePolicy;

        $this->assertTrue($policy->update($this->otherUser, $role));
    }

    public function test_role_policy_delete_requires_permission(): void
    {
        $role = Role::firstOrCreate(['name' => 'test_role', 'guard_name' => 'web']);
        $policy = new RolePolicy;

        $this->assertFalse($policy->delete($this->otherUser, $role));
    }

    public function test_role_policy_delete_granted_with_permission(): void
    {
        $this->otherUser->givePermissionTo('Delete:Role');
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $role = Role::firstOrCreate(['name' => 'test_role', 'guard_name' => 'web']);
        $policy = new RolePolicy;

        $this->assertTrue($policy->delete($this->otherUser, $role));
    }

    public function test_role_policy_restore_requires_permission(): void
    {
        $role = Role::firstOrCreate(['name' => 'test_role', 'guard_name' => 'web']);
        $policy = new RolePolicy;

        $this->assertFalse($policy->restore($this->otherUser, $role));
    }

    public function test_role_policy_restore_granted_with_permission(): void
    {
        $this->otherUser->givePermissionTo('Restore:Role');
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $role = Role::firstOrCreate(['name' => 'test_role', 'guard_name' => 'web']);
        $policy = new RolePolicy;

        $this->assertTrue($policy->restore($this->otherUser, $role));
    }

    public function test_role_policy_force_delete_requires_permission(): void
    {
        $role = Role::firstOrCreate(['name' => 'test_role', 'guard_name' => 'web']);
        $policy = new RolePolicy;

        $this->assertFalse($policy->forceDelete($this->otherUser, $role));
    }

    public function test_role_policy_force_delete_granted_with_permission(): void
    {
        $this->otherUser->givePermissionTo('ForceDelete:Role');
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $role = Role::firstOrCreate(['name' => 'test_role', 'guard_name' => 'web']);
        $policy = new RolePolicy;

        $this->assertTrue($policy->forceDelete($this->otherUser, $role));
    }

    public function test_role_policy_force_delete_any_requires_permission(): void
    {
        $policy = new RolePolicy;

        $this->assertFalse($policy->forceDeleteAny($this->otherUser));
    }

    public function test_role_policy_force_delete_any_granted_with_permission(): void
    {
        $this->otherUser->givePermissionTo('ForceDeleteAny:Role');
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $policy = new RolePolicy;

        $this->assertTrue($policy->forceDeleteAny($this->otherUser));
    }

    public function test_role_policy_restore_any_requires_permission(): void
    {
        $policy = new RolePolicy;

        $this->assertFalse($policy->restoreAny($this->otherUser));
    }

    public function test_role_policy_restore_any_granted_with_permission(): void
    {
        $this->otherUser->givePermissionTo('RestoreAny:Role');
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $policy = new RolePolicy;

        $this->assertTrue($policy->restoreAny($this->otherUser));
    }

    public function test_role_policy_replicate_requires_permission(): void
    {
        $role = Role::firstOrCreate(['name' => 'test_role', 'guard_name' => 'web']);
        $policy = new RolePolicy;

        $this->assertFalse($policy->replicate($this->otherUser, $role));
    }

    public function test_role_policy_replicate_granted_with_permission(): void
    {
        $this->otherUser->givePermissionTo('Replicate:Role');
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $role = Role::firstOrCreate(['name' => 'test_role', 'guard_name' => 'web']);
        $policy = new RolePolicy;

        $this->assertTrue($policy->replicate($this->otherUser, $role));
    }

    public function test_role_policy_reorder_requires_permission(): void
    {
        $policy = new RolePolicy;

        $this->assertFalse($policy->reorder($this->otherUser));
    }

    public function test_role_policy_reorder_granted_with_permission(): void
    {
        $this->otherUser->givePermissionTo('Reorder:Role');
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $policy = new RolePolicy;

        $this->assertTrue($policy->reorder($this->otherUser));
    }

    // ─── RolePolicy Super Admin Access ──────────────────────────

    public function test_role_policy_super_admin_can_view_any(): void
    {
        $policy = new RolePolicy;

        $this->assertTrue($policy->viewAny($this->superAdmin));
    }

    public function test_role_policy_super_admin_can_view(): void
    {
        $role = Role::firstOrCreate(['name' => 'test_role', 'guard_name' => 'web']);
        $policy = new RolePolicy;

        $this->assertTrue($policy->view($this->superAdmin, $role));
    }

    public function test_role_policy_super_admin_can_create(): void
    {
        $policy = new RolePolicy;

        $this->assertTrue($policy->create($this->superAdmin));
    }

    public function test_role_policy_super_admin_can_update(): void
    {
        $role = Role::firstOrCreate(['name' => 'test_role', 'guard_name' => 'web']);
        $policy = new RolePolicy;

        $this->assertTrue($policy->update($this->superAdmin, $role));
    }

    public function test_role_policy_super_admin_can_delete(): void
    {
        $role = Role::firstOrCreate(['name' => 'test_role', 'guard_name' => 'web']);
        $policy = new RolePolicy;

        $this->assertTrue($policy->delete($this->superAdmin, $role));
    }

    // ═══════════════════════════════════════════════════════════
    // SECTION 8: Edge Cases — Owner vs Permission Isolation
    // ═══════════════════════════════════════════════════════════

    #[DataProvider('hubPolicyProvider')]
    public function test_owner_access_does_not_grant_view_any(string $policyClass, string $modelClass, string $entityName): void
    {
        // Owner can view/update/delete own records, but viewAny still requires permission
        $policy = new $policyClass;

        $this->assertFalse($policy->viewAny($this->owner));
    }

    #[DataProvider('hubPolicyProvider')]
    public function test_owner_access_does_not_grant_create(string $policyClass, string $modelClass, string $entityName): void
    {
        // Owner access is per-record; create is permission-based only
        $policy = new $policyClass;

        $this->assertFalse($policy->create($this->owner));
    }

    #[DataProvider('hubPolicyProvider')]
    public function test_owner_access_does_not_grant_restore(string $policyClass, string $modelClass, string $entityName): void
    {
        // restore() is inherited from BasePolicy (no owner override)
        $policy = new $policyClass;
        $record = $this->createRecord($modelClass, $this->owner);

        $this->assertFalse($policy->restore($this->owner, $record));
    }

    #[DataProvider('hubPolicyProvider')]
    public function test_owner_access_does_not_grant_force_delete(string $policyClass, string $modelClass, string $entityName): void
    {
        // forceDelete() is inherited from BasePolicy (no owner override)
        $policy = new $policyClass;
        $record = $this->createRecord($modelClass, $this->owner);

        $this->assertFalse($policy->forceDelete($this->owner, $record));
    }

    #[DataProvider('hubPolicyProvider')]
    public function test_owner_access_does_not_grant_replicate(string $policyClass, string $modelClass, string $entityName): void
    {
        // replicate() is inherited from BasePolicy (no owner override)
        $policy = new $policyClass;
        $record = $this->createRecord($modelClass, $this->owner);

        $this->assertFalse($policy->replicate($this->owner, $record));
    }

    // ═══════════════════════════════════════════════════════════
    // SECTION 9: All Policies Exist and Follow Shield Convention
    // ═══════════════════════════════════════════════════════════

    public function test_all_hub_policies_exist(): void
    {
        $expectedPolicies = [
            RlmPatternPolicy::class,
            RlmFailurePolicy::class,
            RlmLessonPolicy::class,
            FailureReportPolicy::class,
            GenerationTracePolicy::class,
            PreventionRulePolicy::class,
            RlmScorePolicy::class,
        ];

        foreach ($expectedPolicies as $policy) {
            $this->assertTrue(class_exists($policy), "Policy class {$policy} should exist");
        }
    }

    public function test_role_policy_exists(): void
    {
        $this->assertTrue(class_exists(RolePolicy::class));
    }

    public function test_all_hub_policies_have_required_methods(): void
    {
        $policies = [
            RlmPatternPolicy::class,
            RlmFailurePolicy::class,
            RlmLessonPolicy::class,
            FailureReportPolicy::class,
            GenerationTracePolicy::class,
            PreventionRulePolicy::class,
            RlmScorePolicy::class,
        ];

        $requiredMethods = [
            'viewAny', 'view', 'create', 'update', 'delete',
            'restore', 'forceDelete', 'restoreAny', 'forceDeleteAny',
            'replicate', 'reorder',
        ];

        foreach ($policies as $policy) {
            foreach ($requiredMethods as $method) {
                $this->assertTrue(
                    method_exists($policy, $method),
                    "{$policy} is missing method: {$method}"
                );
            }
        }
    }

    public function test_role_policy_has_all_required_methods(): void
    {
        $requiredMethods = [
            'viewAny', 'view', 'create', 'update', 'delete',
            'restore', 'forceDelete', 'restoreAny', 'forceDeleteAny',
            'replicate', 'reorder',
        ];

        foreach ($requiredMethods as $method) {
            $this->assertTrue(
                method_exists(RolePolicy::class, $method),
                "RolePolicy is missing method: {$method}"
            );
        }
    }

    public function test_permission_prefix_uses_shield_pascal_case_format(): void
    {
        // Shield uses Pascal:Case format (e.g., "ViewAny:RlmPattern")
        $prefixes = [
            RlmPatternPolicy::class => 'RlmPattern',
            RlmFailurePolicy::class => 'RlmFailure',
            RlmLessonPolicy::class => 'RlmLesson',
            FailureReportPolicy::class => 'FailureReport',
            GenerationTracePolicy::class => 'GenerationTrace',
            PreventionRulePolicy::class => 'PreventionRule',
            RlmScorePolicy::class => 'RlmScore',
        ];

        foreach ($prefixes as $policyClass => $expectedPrefix) {
            $reflection = new \ReflectionMethod($policyClass, 'permissionPrefix');
            $reflection->setAccessible(true);
            $policy = new $policyClass;
            $actual = $reflection->invoke($policy);

            $this->assertEquals($expectedPrefix, $actual, "{$policyClass} should use prefix '{$expectedPrefix}'");
            $this->assertMatchesRegularExpression(
                '/^[A-Z][a-zA-Z]+$/',
                $actual,
                "Permission prefix '{$actual}' should be PascalCase"
            );
        }
    }
}
