<?php

namespace Aicl\Tests\Feature\Swoole;

use Aicl\Swoole\Cache\PermissionCacheManager;
use Aicl\Swoole\SwooleCache;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PermissionCacheFeatureTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, array<string, array<string, mixed>>> */
    private array $tables = [];

    protected function setUp(): void
    {
        parent::setUp();

        SwooleCache::reset();

        SwooleCache::useClock(fn (): int => Carbon::now()->timestamp);

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

        PermissionCacheManager::register();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $this->seedPermissions();
    }

    protected function tearDown(): void
    {
        SwooleCache::reset();
        $this->tables = [];
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_full_permission_check_flow_populates_cache(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        // First check — cache miss, builds from Spatie
        $this->assertTrue($user->can('ViewAny:User'));

        // Verify cache was populated
        $cached = SwooleCache::get('permissions', "user:{$user->id}");
        $this->assertNotNull($cached);
        $this->assertContains('ViewAny:User', $cached['permissions']);
        $this->assertContains('admin', $cached['roles']);
        $this->assertFalse($cached['super_admin']);

        // Subsequent checks should use cache (no additional DB queries)
        $this->assertTrue($user->can('Create:User'));
        $this->assertTrue($user->can('Delete:User'));
        $this->assertFalse($cached['super_admin']); // admin cannot force delete
    }

    public function test_role_change_invalidation_and_rebuild(): void
    {
        $user = User::factory()->create();
        $user->assignRole('viewer');

        // Populate cache via permission check
        $this->assertTrue($user->can('ViewAny:User'));
        $this->assertNotNull(SwooleCache::get('permissions', "user:{$user->id}"));

        // Viewer cannot create
        $cached = SwooleCache::get('permissions', "user:{$user->id}");
        $this->assertNotContains('Create:User', $cached['permissions']);

        // Upgrade to admin — invalidates cache
        $user->syncRoles(['admin']);

        // Cache should be cleared
        $this->assertNull(SwooleCache::get('permissions', "user:{$user->id}"));

        // Refresh user model for fresh relationships
        $user = $user->fresh();

        // Next check rebuilds cache with new role
        $this->assertTrue($user->can('Create:User'));

        $cached = SwooleCache::get('permissions', "user:{$user->id}");
        $this->assertContains('admin', $cached['roles']);
        $this->assertContains('Create:User', $cached['permissions']);
    }

    public function test_policy_fallback_works_with_cache(): void
    {
        $user = User::factory()->create();
        // User has no roles — no Spatie permissions

        // Populate cache (empty permissions)
        $user->can('ViewAny:User');

        $cached = SwooleCache::get('permissions', "user:{$user->id}");
        $this->assertNotNull($cached);
        $this->assertSame([], $cached['permissions']);

        // UserPolicy allows self-view/self-update — this goes through the policy
        // because Gate::before returns null for abilities not in cache
        $this->assertTrue($user->can('view', $user));
        $this->assertTrue($user->can('update', $user));

        // But cannot view other users (no permission, no policy override)
        $other = User::factory()->create();
        $this->assertFalse($user->can('view', $other));
    }

    public function test_super_admin_cached_bypass(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');

        // First check populates cache
        $this->assertTrue($user->can('ViewAny:User'));

        $cached = SwooleCache::get('permissions', "user:{$user->id}");
        $this->assertTrue($cached['super_admin']);

        // super_admin bypasses for any ability (even non-existent ones)
        $this->assertTrue($user->can('ArbitraryNonexistentAbility'));
    }

    public function test_multiple_users_independent_caches(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $viewer = User::factory()->create();
        $viewer->assignRole('viewer');

        // Both populate their caches
        $admin->can('ViewAny:User');
        $viewer->can('ViewAny:User');

        $adminCache = SwooleCache::get('permissions', "user:{$admin->id}");
        $viewerCache = SwooleCache::get('permissions', "user:{$viewer->id}");

        // Admin has more permissions than viewer
        $this->assertContains('Create:User', $adminCache['permissions']);
        $this->assertNotContains('Create:User', $viewerCache['permissions']);

        // Changing admin's role doesn't affect viewer
        $admin->syncRoles(['viewer']);
        $this->assertNull(SwooleCache::get('permissions', "user:{$admin->id}"));
        $this->assertNotNull(SwooleCache::get('permissions', "user:{$viewer->id}"));
    }

    public function test_cache_count_reflects_populated_entries(): void
    {
        $user1 = User::factory()->create();
        $user1->assignRole('admin');

        $user2 = User::factory()->create();
        $user2->assignRole('viewer');

        $this->assertSame(0, SwooleCache::count('permissions'));

        $user1->can('ViewAny:User');
        $this->assertSame(1, SwooleCache::count('permissions'));

        $user2->can('ViewAny:User');
        $this->assertSame(2, SwooleCache::count('permissions'));
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
            public function __construct(private array &$data) {}

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

            public function getIterator(): \ArrayIterator
            {
                return new \ArrayIterator($this->data);
            }
        };
    }
}
