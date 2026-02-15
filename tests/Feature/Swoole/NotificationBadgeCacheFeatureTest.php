<?php

namespace Aicl\Tests\Feature\Swoole;

use Aicl\Swoole\Cache\NotificationBadgeCacheManager;
use Aicl\Swoole\SwooleCache;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationBadgeCacheFeatureTest extends TestCase
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

    public function test_full_cache_flow_populate_hit_invalidate_rebuild(): void
    {
        $user = \App\Models\User::factory()->create();

        // Create initial notifications
        $this->createNotification($user->id, unread: true);
        $this->createNotification($user->id, unread: true);

        // First call — cache miss, computes from DB
        $badge = NotificationBadgeCacheManager::getBadge($user->id);
        $this->assertSame('2', $badge);

        // Verify cache populated
        $cached = SwooleCache::get('notification_badges', "user:{$user->id}");
        $this->assertNotNull($cached);
        $this->assertSame(2, $cached['count']);

        // Second call — cache hit
        $badge2 = NotificationBadgeCacheManager::getBadge($user->id);
        $this->assertSame('2', $badge2);

        // Create new notification — triggers invalidation
        $this->createNotification($user->id, unread: true);

        // Cache should be invalidated
        $this->assertNull(SwooleCache::get('notification_badges', "user:{$user->id}"));

        // Third call — cache miss, recomputes
        $badge3 = NotificationBadgeCacheManager::getBadge($user->id);
        $this->assertSame('3', $badge3);
    }

    public function test_mark_as_read_invalidates_and_decrements(): void
    {
        $user = \App\Models\User::factory()->create();

        $notification1 = $this->createNotification($user->id, unread: true);
        $this->createNotification($user->id, unread: true);

        // Populate cache
        $this->assertSame('2', NotificationBadgeCacheManager::getBadge($user->id));

        // Mark one as read
        $notification1->update(['read_at' => now()]);

        // Cache invalidated
        $this->assertNull(SwooleCache::get('notification_badges', "user:{$user->id}"));

        // Recomputes with correct count
        $this->assertSame('1', NotificationBadgeCacheManager::getBadge($user->id));
    }

    public function test_delete_notification_invalidates_cache(): void
    {
        $user = \App\Models\User::factory()->create();

        $notification = $this->createNotification($user->id, unread: true);
        $this->createNotification($user->id, unread: true);

        // Populate cache
        $this->assertSame('2', NotificationBadgeCacheManager::getBadge($user->id));

        // Delete one
        $notification->delete();

        // Cache invalidated, recomputes
        $this->assertSame('1', NotificationBadgeCacheManager::getBadge($user->id));
    }

    public function test_per_user_isolation(): void
    {
        $user1 = \App\Models\User::factory()->create();
        $user2 = \App\Models\User::factory()->create();

        $this->createNotification($user1->id, unread: true);
        $this->createNotification($user2->id, unread: true);
        $this->createNotification($user2->id, unread: true);

        // Populate both caches
        $this->assertSame('1', NotificationBadgeCacheManager::getBadge($user1->id));
        $this->assertSame('2', NotificationBadgeCacheManager::getBadge($user2->id));

        // New notification for user2 only
        $this->createNotification($user2->id, unread: true);

        // User1's cache is intact, user2's invalidated
        $this->assertNotNull(SwooleCache::get('notification_badges', "user:{$user1->id}"));
        $this->assertNull(SwooleCache::get('notification_badges', "user:{$user2->id}"));

        // User2 recomputes with new count
        $this->assertSame('3', NotificationBadgeCacheManager::getBadge($user2->id));
    }

    public function test_non_octane_environment_falls_through_to_direct_query(): void
    {
        SwooleCache::reset();

        // Re-register all tables without resolver
        SwooleCache::register('widget_stats', rows: 100, ttl: 60, valueSize: 2000);
        SwooleCache::register('permissions', rows: 2000, ttl: 300, valueSize: 5000);
        SwooleCache::register('rlm_stats', rows: 10, ttl: 300, valueSize: 5000);
        SwooleCache::register('service_health', rows: 10, ttl: 30, valueSize: 200);
        SwooleCache::register('notification_badges', rows: 1000, ttl: 60, valueSize: 100);
        $this->assertFalse(SwooleCache::isAvailable());

        $user = \App\Models\User::factory()->create();
        $this->createNotification($user->id, unread: true);

        $result = NotificationBadgeCacheManager::getBadge($user->id);
        $this->assertSame('1', $result);

        // Re-set resolver for tearDown
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
            'id' => (string) Str::uuid(),
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
