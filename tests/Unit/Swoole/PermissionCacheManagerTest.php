<?php

namespace Aicl\Tests\Unit\Swoole;

use Aicl\Swoole\Cache\PermissionCacheManager;
use Aicl\Swoole\SwooleCache;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Events\PermissionAttached;
use Spatie\Permission\Events\PermissionDetached;
use Spatie\Permission\Events\RoleAttached;
use Spatie\Permission\Events\RoleDetached;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PermissionCacheManagerTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, array<string, array<string, mixed>>> */
    private array $tables = [];

    protected function setUp(): void
    {
        parent::setUp();

        SwooleCache::reset();

        // Use Carbon as the clock source so setTestNow works
        /** @phpstan-ignore-next-line */
        SwooleCache::useClock(fn (): int => Carbon::now()->timestamp);

        // Inject a mock resolver that uses in-memory arrays
        /** @phpstan-ignore-next-line */
        SwooleCache::useResolver(function (string $table): ?object {
            if (! isset($this->tables[$table])) {
                $this->tables[$table] = [];
            }

            return $this->createMockTable($table);
        });

        // Register all cache tables since service provider event listeners
        // from other managers survive SwooleCache::reset() and may fire
        // when Eloquent models are created during tests.
        SwooleCache::register('widget_stats', rows: 100, ttl: 60, valueSize: 2000);
        SwooleCache::register('notification_badges', rows: 1000, ttl: 60, valueSize: 100);
        SwooleCache::register('rlm_stats', rows: 10, ttl: 300, valueSize: 5000);
        SwooleCache::register('service_health', rows: 10, ttl: 30, valueSize: 200);

        // Register the permission cache (this also registers Gate::before)
        PermissionCacheManager::register();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $this->seedPermissions();
    }

    protected function tearDown(): void
    {
        SwooleCache::reset();
        $this->tables = [];

        parent::tearDown();
    }

    // -- buildCacheForUser --

    public function test_build_cache_for_user_returns_correct_structure(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        // Refresh to load roles relationship
        $user = $user->fresh();

        /** @phpstan-ignore-next-line */
        $cache = PermissionCacheManager::buildCacheForUser($user);

        $this->assertArrayHasKey('permissions', $cache);
        $this->assertArrayHasKey('roles', $cache);
        $this->assertArrayHasKey('super_admin', $cache);
        $this->assertContains('admin', $cache['roles']);
        $this->assertFalse($cache['super_admin']);
        $this->assertContains('ViewAny:User', $cache['permissions']);
    }

    public function test_build_cache_for_super_admin_sets_flag(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $user = $user->fresh();

        /** @phpstan-ignore-next-line */
        $cache = PermissionCacheManager::buildCacheForUser($user);

        $this->assertTrue($cache['super_admin']);
        $this->assertContains('super_admin', $cache['roles']);
    }

    public function test_build_cache_for_user_without_roles_trait_returns_empty(): void
    {
        // Create an Authorizable mock without HasRoles
        $mock = new class implements Authorizable
        {
            /** @phpstan-ignore-next-line */
            public function can($abilities, $arguments = []): bool
            {
                return false;
            }
        };

        $cache = PermissionCacheManager::buildCacheForUser($mock);

        $this->assertSame([], $cache['permissions']);
        $this->assertSame([], $cache['roles']);
        $this->assertFalse($cache['super_admin']);
    }

    // -- Gate interceptor --

    public function test_cache_miss_builds_and_stores_on_first_can_check(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        // Cache should be empty before the check
        $this->assertNull(SwooleCache::get('permissions', "user:{$user->id}"));

        // This triggers Gate::before → cache miss → build → store
        $result = $user->can('ViewAny:User');

        $this->assertTrue($result);

        // Now the cache should be populated
        $cached = SwooleCache::get('permissions', "user:{$user->id}");
        /** @phpstan-ignore-next-line */
        $this->assertNotNull($cached);
        $this->assertContains('ViewAny:User', $cached['permissions']);
    }

    public function test_cache_hit_returns_from_swoole_cache(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        // Pre-populate cache
        SwooleCache::set('permissions', "user:{$user->id}", [
            'permissions' => ['ViewAny:User', 'Create:User'],
            'roles' => ['admin'],
            'super_admin' => false,
        ]);

        // Should get true from cache without hitting DB
        $this->assertTrue($user->can('ViewAny:User'));
        $this->assertTrue($user->can('Create:User'));
    }

    public function test_super_admin_bypass_returns_true_for_any_ability(): void
    {
        $user = User::factory()->create();

        // Pre-populate cache with super_admin flag
        SwooleCache::set('permissions', "user:{$user->id}", [
            'permissions' => [],
            'roles' => ['super_admin'],
            'super_admin' => true,
        ]);

        // super_admin should get true for any arbitrary ability
        $this->assertTrue($user->can('SomeArbitraryAbility'));
        $this->assertTrue($user->can('Delete:AnythingAtAll'));
    }

    public function test_permission_not_in_cache_returns_null_allows_policy(): void
    {
        $user = User::factory()->create();

        // Pre-populate cache with limited permissions
        SwooleCache::set('permissions', "user:{$user->id}", [
            'permissions' => ['ViewAny:User'],
            'roles' => ['viewer'],
            'super_admin' => false,
        ]);

        // The user should be able to view themselves via UserPolicy (self-view)
        // even though 'view' isn't in their Spatie permissions
        $this->assertTrue($user->can('view', $user));
    }

    public function test_ttl_expiry_causes_cache_rebuild(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        // First call populates cache
        $user->can('ViewAny:User');
        $this->assertNotNull(SwooleCache::get('permissions', "user:{$user->id}"));

        // Advance time past TTL (300 seconds)
        Carbon::setTestNow(Carbon::now()->addSeconds(301));

        // Cache should now be expired
        $this->assertNull(SwooleCache::get('permissions', "user:{$user->id}"));

        // Next call should rebuild cache
        $result = $user->can('ViewAny:User');
        $this->assertTrue($result);

        // Cache should be repopulated
        $this->assertNotNull(SwooleCache::get('permissions', "user:{$user->id}"));

        Carbon::setTestNow();
    }

    // -- Invalidation --

    public function test_role_attached_invalidates_user_cache(): void
    {
        $user = User::factory()->create();

        // Pre-populate cache
        SwooleCache::set('permissions', "user:{$user->id}", [
            'permissions' => ['ViewAny:User'],
            'roles' => ['viewer'],
            'super_admin' => false,
        ]);

        $this->assertNotNull(SwooleCache::get('permissions', "user:{$user->id}"));

        // Assign a role — this fires RoleAttached event
        $user->assignRole('admin');

        // Cache should be invalidated
        $this->assertNull(SwooleCache::get('permissions', "user:{$user->id}"));
    }

    public function test_role_detached_invalidates_user_cache(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        // Pre-populate cache
        SwooleCache::set('permissions', "user:{$user->id}", [
            'permissions' => ['ViewAny:User', 'Create:User'],
            'roles' => ['admin'],
            'super_admin' => false,
        ]);

        $this->assertNotNull(SwooleCache::get('permissions', "user:{$user->id}"));

        // Remove role — fires RoleDetached event
        $user->removeRole('admin');

        // Cache should be invalidated
        $this->assertNull(SwooleCache::get('permissions', "user:{$user->id}"));
    }

    public function test_permission_attached_invalidates_user_cache(): void
    {
        $user = User::factory()->create();

        // Pre-populate cache
        SwooleCache::set('permissions', "user:{$user->id}", [
            'permissions' => [],
            'roles' => [],
            'super_admin' => false,
        ]);

        $this->assertNotNull(SwooleCache::get('permissions', "user:{$user->id}"));

        // Direct permission grant — fires PermissionAttached event
        $user->givePermissionTo('ViewAny:User');

        // Cache should be invalidated
        $this->assertNull(SwooleCache::get('permissions', "user:{$user->id}"));
    }

    public function test_permission_detached_invalidates_user_cache(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('ViewAny:User');

        // Pre-populate cache
        SwooleCache::set('permissions', "user:{$user->id}", [
            'permissions' => ['ViewAny:User'],
            'roles' => [],
            'super_admin' => false,
        ]);

        $this->assertNotNull(SwooleCache::get('permissions', "user:{$user->id}"));

        // Revoke direct permission — fires PermissionDetached event
        $user->revokePermissionTo('ViewAny:User');

        // Cache should be invalidated
        $this->assertNull(SwooleCache::get('permissions', "user:{$user->id}"));
    }

    public function test_role_change_does_not_affect_other_users_cache(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        // Pre-populate cache for both users
        SwooleCache::set('permissions', "user:{$user1->id}", [
            'permissions' => ['ViewAny:User'],
            'roles' => ['viewer'],
            'super_admin' => false,
        ]);
        SwooleCache::set('permissions', "user:{$user2->id}", [
            'permissions' => ['ViewAny:User'],
            'roles' => ['viewer'],
            'super_admin' => false,
        ]);

        // Change user1's role
        $user1->assignRole('admin');

        // user1's cache should be invalidated
        $this->assertNull(SwooleCache::get('permissions', "user:{$user1->id}"));

        // user2's cache should still be present
        $this->assertNotNull(SwooleCache::get('permissions', "user:{$user2->id}"));
    }

    // -- Global flush on permission/role definition changes --

    public function test_new_permission_created_flushes_all_caches(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        // Pre-populate cache for both users
        SwooleCache::set('permissions', "user:{$user1->id}", [
            'permissions' => ['ViewAny:User'],
            'roles' => ['admin'],
            'super_admin' => false,
        ]);
        SwooleCache::set('permissions', "user:{$user2->id}", [
            'permissions' => ['ViewAny:User'],
            'roles' => ['viewer'],
            'super_admin' => false,
        ]);

        // Create a new permission — fires eloquent.created event
        Permission::create(['name' => 'NewAbility:Test', 'guard_name' => 'web']);

        // Both users' caches should be flushed
        $this->assertNull(SwooleCache::get('permissions', "user:{$user1->id}"));
        $this->assertNull(SwooleCache::get('permissions', "user:{$user2->id}"));
    }

    public function test_permission_deleted_flushes_all_caches(): void
    {
        $user = User::factory()->create();

        SwooleCache::set('permissions', "user:{$user->id}", [
            'permissions' => ['ViewAny:User'],
            'roles' => ['admin'],
            'super_admin' => false,
        ]);

        // Delete a permission
        /** @phpstan-ignore-next-line */
        Permission::where('name', 'ViewAny:User')->first()->delete();

        $this->assertNull(SwooleCache::get('permissions', "user:{$user->id}"));
    }

    public function test_role_deleted_flushes_all_caches(): void
    {
        $user = User::factory()->create();

        SwooleCache::set('permissions', "user:{$user->id}", [
            'permissions' => ['ViewAny:User'],
            'roles' => ['admin'],
            'super_admin' => false,
        ]);

        // Delete a role — fires eloquent.deleted event on Role model
        /** @phpstan-ignore-next-line */
        Role::where('name', 'viewer')->first()->delete();

        $this->assertNull(SwooleCache::get('permissions', "user:{$user->id}"));
    }

    // -- Graceful degradation --

    public function test_non_octane_environment_falls_through_to_spatie(): void
    {
        // Reset and re-register WITHOUT the mock resolver
        SwooleCache::reset();
        SwooleCache::register('widget_stats', rows: 100, ttl: 60, valueSize: 2000);
        SwooleCache::register('notification_badges', rows: 1000, ttl: 60, valueSize: 100);
        SwooleCache::register('rlm_stats', rows: 10, ttl: 300, valueSize: 5000);
        SwooleCache::register('service_health', rows: 10, ttl: 30, valueSize: 200);
        PermissionCacheManager::register();

        // SwooleCache::isAvailable() is now false (no Swoole worker context)
        $this->assertFalse(SwooleCache::isAvailable());

        $user = User::factory()->create();
        $user->assignRole('admin');

        // Should still work via Spatie's normal path
        $this->assertTrue($user->can('ViewAny:User'));
        $this->assertFalse($user->can('ForceDelete:User'));

        // Re-set the resolver for tearDown
        /** @phpstan-ignore-next-line */
        SwooleCache::useResolver(function (string $table): ?object {
            if (! isset($this->tables[$table])) {
                $this->tables[$table] = [];
            }

            return $this->createMockTable($table);
        });
    }

    // -- Constants --

    public function test_table_constants_are_correct(): void
    {
        $this->assertSame('permissions', PermissionCacheManager::TABLE_NAME);
        $this->assertSame(2000, PermissionCacheManager::TABLE_ROWS);
        $this->assertSame(300, PermissionCacheManager::TABLE_TTL);
        $this->assertSame(5000, PermissionCacheManager::TABLE_VALUE_SIZE);
    }

    public function test_table_is_registered_with_correct_params(): void
    {
        $registrations = SwooleCache::registrations();

        $this->assertArrayHasKey('permissions', $registrations);
        $this->assertSame(2000, $registrations['permissions']['rows']);
        $this->assertSame(300, $registrations['permissions']['ttl']);
        $this->assertSame(5000, $registrations['permissions']['valueSize']);
    }

    // -- Helpers --

    protected function seedPermissions(): void
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
        $viewer = Role::firstOrCreate(['name' => 'viewer', 'guard_name' => 'web']);

        $superAdmin->syncPermissions(Permission::where('guard_name', 'web')->get());
        $admin->syncPermissions(
            Permission::where('guard_name', 'web')
                ->where('name', 'not like', 'ForceDelete%')
                ->get()
        );
        $viewer->syncPermissions(
            Permission::where('guard_name', 'web')
                ->where('name', 'like', 'View%')
                ->get()
        );
    }

    private function createMockTable(string $tableName): object
    {
        $data = &$this->tables[$tableName];

        return new class($data) implements \Countable, \IteratorAggregate
        {
            /** @phpstan-ignore-next-line */
            public function __construct(private array &$data) {}

            /** @phpstan-ignore-next-line */
            public function set(string $key, array $value): bool
            {
                $this->data[$key] = $value;

                return true;
            }

            /**
             * @return array<string, mixed>|false
             */
            public function get(string $key, ?string $field = null): array|false
            {
                if (! isset($this->data[$key])) {
                    return false;
                }

                if ($field !== null) {
                    return $this->data[$key][$field] ?? false;
                }

                return $this->data[$key];
            }

            public function del(string $key): bool
            {
                if (! isset($this->data[$key])) {
                    return false;
                }

                unset($this->data[$key]);

                return true;
            }

            public function exist(string $key): bool
            {
                return isset($this->data[$key]);
            }

            public function count(): int
            {
                return count($this->data);
            }

            /** @phpstan-ignore-next-line */
            public function getIterator(): \ArrayIterator
            {
                return new \ArrayIterator($this->data);
            }
        };
    }
}
