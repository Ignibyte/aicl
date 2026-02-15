<?php

namespace Aicl\Tests\Unit\Swoole;

use Aicl\Swoole\Cache\NotificationBadgeCacheManager;
use Aicl\Swoole\SwooleCache;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Tests\TestCase;

class NotificationBadgeCacheManagerTest extends TestCase
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
        SwooleCache::register('permissions', rows: 2000, ttl: 300, valueSize: 5000);
        SwooleCache::register('rlm_stats', rows: 10, ttl: 300, valueSize: 5000);
        SwooleCache::register('service_health', rows: 10, ttl: 30, valueSize: 200);

        NotificationBadgeCacheManager::register();
    }

    protected function tearDown(): void
    {
        SwooleCache::reset();
        $this->tables = [];
        Carbon::setTestNow();

        parent::tearDown();
    }

    // -- Constants --

    public function test_table_name_is_notification_badges(): void
    {
        $this->assertSame('notification_badges', NotificationBadgeCacheManager::TABLE_NAME);
    }

    public function test_table_rows_is_1000(): void
    {
        $this->assertSame(1000, NotificationBadgeCacheManager::TABLE_ROWS);
    }

    public function test_table_ttl_is_60(): void
    {
        $this->assertSame(60, NotificationBadgeCacheManager::TABLE_TTL);
    }

    public function test_table_value_size_is_100(): void
    {
        $this->assertSame(100, NotificationBadgeCacheManager::TABLE_VALUE_SIZE);
    }

    // -- Registration --

    public function test_register_creates_swoole_cache_table(): void
    {
        $registrations = SwooleCache::registrations();

        $this->assertArrayHasKey('notification_badges', $registrations);
        $this->assertSame(1000, $registrations['notification_badges']['rows']);
        $this->assertSame(60, $registrations['notification_badges']['ttl']);
        $this->assertSame(100, $registrations['notification_badges']['valueSize']);
    }

    // -- getBadge --

    public function test_get_badge_returns_null_for_null_user_id(): void
    {
        $this->assertNull(NotificationBadgeCacheManager::getBadge(null));
    }

    public function test_get_badge_returns_null_when_no_unread_notifications(): void
    {
        $user = \App\Models\User::factory()->create();

        $result = NotificationBadgeCacheManager::getBadge($user->id);

        $this->assertNull($result);
    }

    public function test_get_badge_returns_count_string_when_unread_exist(): void
    {
        $user = \App\Models\User::factory()->create();

        $this->createNotification($user->id, unread: true);
        $this->createNotification($user->id, unread: true);
        $this->createNotification($user->id, unread: true);

        $result = NotificationBadgeCacheManager::getBadge($user->id);

        $this->assertSame('3', $result);
    }

    public function test_get_badge_excludes_read_notifications(): void
    {
        $user = \App\Models\User::factory()->create();

        $this->createNotification($user->id, unread: true);
        $this->createNotification($user->id, unread: true);
        $this->createNotification($user->id, unread: false); // read

        $result = NotificationBadgeCacheManager::getBadge($user->id);

        $this->assertSame('2', $result);
    }

    public function test_get_badge_caches_result(): void
    {
        $user = \App\Models\User::factory()->create();

        $this->createNotification($user->id, unread: true);

        // First call — cache miss, computes from DB
        NotificationBadgeCacheManager::getBadge($user->id);

        // Verify cached
        $cached = SwooleCache::get('notification_badges', "user:{$user->id}");
        $this->assertNotNull($cached);
        $this->assertSame(1, $cached['count']);
    }

    public function test_get_badge_returns_cached_value_on_hit(): void
    {
        $user = \App\Models\User::factory()->create();

        // Pre-populate cache with a known value
        SwooleCache::set('notification_badges', "user:{$user->id}", ['count' => 42]);

        // Should return cached value regardless of DB state
        $result = NotificationBadgeCacheManager::getBadge($user->id);

        $this->assertSame('42', $result);
    }

    public function test_get_badge_returns_null_for_cached_zero_count(): void
    {
        $user = \App\Models\User::factory()->create();

        SwooleCache::set('notification_badges', "user:{$user->id}", ['count' => 0]);

        $result = NotificationBadgeCacheManager::getBadge($user->id);

        $this->assertNull($result);
    }

    public function test_get_badge_only_counts_notifications_for_given_user(): void
    {
        $user1 = \App\Models\User::factory()->create();
        $user2 = \App\Models\User::factory()->create();

        $this->createNotification($user1->id, unread: true);
        $this->createNotification($user1->id, unread: true);
        $this->createNotification($user2->id, unread: true);
        $this->createNotification($user2->id, unread: true);
        $this->createNotification($user2->id, unread: true);

        $this->assertSame('2', NotificationBadgeCacheManager::getBadge($user1->id));
        $this->assertSame('3', NotificationBadgeCacheManager::getBadge($user2->id));
    }

    // -- TTL Expiry --

    public function test_cached_badge_expires_after_ttl(): void
    {
        $user = \App\Models\User::factory()->create();

        $this->createNotification($user->id, unread: true);

        NotificationBadgeCacheManager::getBadge($user->id);
        $this->assertNotNull(SwooleCache::get('notification_badges', "user:{$user->id}"));

        // Advance time past TTL (60s)
        Carbon::setTestNow(now()->addSeconds(61));

        $this->assertNull(SwooleCache::get('notification_badges', "user:{$user->id}"));
    }

    // -- Invalidation --

    public function test_creating_notification_invalidates_user_cache(): void
    {
        $user = \App\Models\User::factory()->create();

        // Populate cache
        NotificationBadgeCacheManager::getBadge($user->id);
        $this->assertNotNull(SwooleCache::get('notification_badges', "user:{$user->id}"));

        // Create a new notification — should invalidate
        $this->createNotification($user->id, unread: true);

        $this->assertNull(SwooleCache::get('notification_badges', "user:{$user->id}"));
    }

    public function test_marking_notification_as_read_invalidates_user_cache(): void
    {
        $user = \App\Models\User::factory()->create();

        $notification = $this->createNotification($user->id, unread: true);

        // Populate cache
        NotificationBadgeCacheManager::getBadge($user->id);
        $this->assertNotNull(SwooleCache::get('notification_badges', "user:{$user->id}"));

        // Mark as read — triggers update event
        $notification->update(['read_at' => now()]);

        $this->assertNull(SwooleCache::get('notification_badges', "user:{$user->id}"));
    }

    public function test_deleting_notification_invalidates_user_cache(): void
    {
        $user = \App\Models\User::factory()->create();

        $notification = $this->createNotification($user->id, unread: true);

        // Populate cache
        NotificationBadgeCacheManager::getBadge($user->id);
        $this->assertNotNull(SwooleCache::get('notification_badges', "user:{$user->id}"));

        // Delete — should invalidate
        $notification->delete();

        $this->assertNull(SwooleCache::get('notification_badges', "user:{$user->id}"));
    }

    public function test_notification_for_one_user_does_not_invalidate_another_users_cache(): void
    {
        $user1 = \App\Models\User::factory()->create();
        $user2 = \App\Models\User::factory()->create();

        // Populate both caches
        NotificationBadgeCacheManager::getBadge($user1->id);
        NotificationBadgeCacheManager::getBadge($user2->id);

        $this->assertNotNull(SwooleCache::get('notification_badges', "user:{$user1->id}"));
        $this->assertNotNull(SwooleCache::get('notification_badges', "user:{$user2->id}"));

        // Create notification for user1 only
        $this->createNotification($user1->id, unread: true);

        // User1's cache invalidated, user2's intact
        $this->assertNull(SwooleCache::get('notification_badges', "user:{$user1->id}"));
        $this->assertNotNull(SwooleCache::get('notification_badges', "user:{$user2->id}"));
    }

    // -- invalidateForUser --

    public function test_invalidate_for_user_removes_cache_entry(): void
    {
        $user = \App\Models\User::factory()->create();

        SwooleCache::set('notification_badges', "user:{$user->id}", ['count' => 5]);

        NotificationBadgeCacheManager::invalidateForUser($user->id);

        $this->assertNull(SwooleCache::get('notification_badges', "user:{$user->id}"));
    }

    // -- Graceful Degradation --

    public function test_get_badge_falls_through_when_swoole_unavailable(): void
    {
        SwooleCache::reset();

        // Re-register all tables but without resolver — SwooleCache unavailable
        SwooleCache::register('widget_stats', rows: 100, ttl: 60, valueSize: 2000);
        SwooleCache::register('permissions', rows: 2000, ttl: 300, valueSize: 5000);
        SwooleCache::register('rlm_stats', rows: 10, ttl: 300, valueSize: 5000);
        SwooleCache::register('service_health', rows: 10, ttl: 30, valueSize: 200);
        SwooleCache::register('notification_badges', rows: 1000, ttl: 60, valueSize: 100);
        $this->assertFalse(SwooleCache::isAvailable());

        $user = \App\Models\User::factory()->create();
        $this->createNotification($user->id, unread: true);
        $this->createNotification($user->id, unread: true);

        $result = NotificationBadgeCacheManager::getBadge($user->id);

        $this->assertSame('2', $result);

        // Re-set the resolver for tearDown
        SwooleCache::useResolver(function (string $table): ?object {
            if (! isset($this->tables[$table])) {
                $this->tables[$table] = [];
            }

            return $this->createMockTable($table);
        });
    }

    // -- Helpers --

    private function createNotification(int $userId, bool $unread = true): DatabaseNotification
    {
        return DatabaseNotification::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'type' => 'App\\Notifications\\TestNotification',
            'notifiable_type' => 'App\\Models\\User',
            'notifiable_id' => $userId,
            'data' => ['title' => 'Test', 'body' => 'Test notification'],
            'read_at' => $unread ? null : now(),
        ]);
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
