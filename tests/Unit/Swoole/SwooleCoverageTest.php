<?php

namespace Aicl\Tests\Unit\Swoole;

use Aicl\Swoole\Cache\NotificationBadgeCacheManager;
use Aicl\Swoole\Cache\PermissionCacheManager;
use Aicl\Swoole\Cache\ServiceHealthCacheManager;
use Aicl\Swoole\Concurrent;
use Aicl\Swoole\Exceptions\ConcurrentException;
use Aicl\Swoole\Exceptions\ConcurrentTimeoutException;
use Aicl\Swoole\Listeners\RestoreSwooleTimers;
use Aicl\Swoole\Listeners\WarmSwooleCaches;
use Aicl\Swoole\SwooleCache;
use Aicl\Swoole\SwooleTimer;
use Laravel\Octane\Events\WorkerStarting;
use ReflectionClass;
use ReflectionMethod;
use RuntimeException;
use Tests\TestCase;

class SwooleCoverageTest extends TestCase
{
    // ========================================================================
    // Concurrent — static method signatures
    // ========================================================================

    public function test_concurrent_has_run_static_method(): void
    {
        $ref = new ReflectionMethod(Concurrent::class, 'run');

        $this->assertTrue($ref->isStatic());
        $this->assertTrue($ref->isPublic());
        /** @phpstan-ignore-next-line */
        $this->assertSame('array', $ref->getReturnType()->getName());
    }

    public function test_concurrent_has_map_static_method(): void
    {
        $ref = new ReflectionMethod(Concurrent::class, 'map');

        $this->assertTrue($ref->isStatic());
        $this->assertTrue($ref->isPublic());
        /** @phpstan-ignore-next-line */
        $this->assertSame('array', $ref->getReturnType()->getName());
    }

    public function test_concurrent_has_race_static_method(): void
    {
        $ref = new ReflectionMethod(Concurrent::class, 'race');

        $this->assertTrue($ref->isStatic());
        $this->assertTrue($ref->isPublic());
    }

    public function test_concurrent_has_is_available_method(): void
    {
        $ref = new ReflectionMethod(Concurrent::class, 'isAvailable');

        $this->assertTrue($ref->isStatic());
        $this->assertTrue($ref->isPublic());
        /** @phpstan-ignore-next-line */
        $this->assertSame('bool', $ref->getReturnType()->getName());
    }

    public function test_concurrent_run_accepts_callables_and_timeout_params(): void
    {
        $ref = new ReflectionMethod(Concurrent::class, 'run');
        $params = $ref->getParameters();

        $this->assertSame('callables', $params[0]->getName());
        $this->assertSame('timeout', $params[1]->getName());
        $this->assertTrue($params[1]->allowsNull());
    }

    public function test_concurrent_map_accepts_items_fn_concurrency_timeout(): void
    {
        $ref = new ReflectionMethod(Concurrent::class, 'map');
        $params = $ref->getParameters();

        $this->assertCount(4, $params);
        $this->assertSame('items', $params[0]->getName());
        $this->assertSame('fn', $params[1]->getName());
        $this->assertSame('concurrency', $params[2]->getName());
        $this->assertSame('timeout', $params[3]->getName());
    }

    public function test_concurrent_race_with_single_callable_returns_its_result(): void
    {
        $result = Concurrent::race([
            'only' => fn () => 'sole-winner',
        ]);

        $this->assertSame('sole-winner', $result);
    }

    public function test_concurrent_map_with_concurrency_one(): void
    {
        $results = Concurrent::map([10, 20, 30], fn (int $item) => $item + 1, concurrency: 1);

        $this->assertSame([0 => 11, 1 => 21, 2 => 31], $results);
    }

    // ========================================================================
    // ConcurrentException — structure
    // ========================================================================

    public function test_concurrent_exception_extends_runtime_exception(): void
    {
        $ref = new ReflectionClass(ConcurrentException::class);

        $this->assertTrue($ref->isSubclassOf(RuntimeException::class));
    }

    public function test_concurrent_exception_has_result_and_exception_accessors(): void
    {
        $ref = new ReflectionClass(ConcurrentException::class);
        $this->assertTrue($ref->hasMethod('getResults'));
        $this->assertTrue($ref->hasMethod('getExceptions'));
        $this->assertTrue($ref->hasMethod('hasResult'));
        $this->assertTrue($ref->hasMethod('hasException'));
    }

    public function test_concurrent_timeout_exception_extends_concurrent_exception(): void
    {
        $ref = new ReflectionClass(ConcurrentTimeoutException::class);

        $this->assertTrue($ref->isSubclassOf(ConcurrentException::class));
    }

    public function test_concurrent_timeout_exception_has_after_factory(): void
    {
        $ref = new ReflectionMethod(ConcurrentTimeoutException::class, 'after');

        $this->assertTrue($ref->isStatic());
        $this->assertTrue($ref->isPublic());
    }

    public function test_concurrent_timeout_exception_after_creates_instance(): void
    {
        $e = ConcurrentTimeoutException::after(5.0, ['a' => 1], ['b' => new RuntimeException('x')]);

        $this->assertInstanceOf(ConcurrentTimeoutException::class, $e);
        $this->assertStringContainsString('5', $e->getMessage());
        $this->assertSame(['a' => 1], $e->getResults());
        $this->assertCount(1, $e->getExceptions());
    }

    // ========================================================================
    // SwooleCache — structural coverage
    // ========================================================================

    public function test_swoole_cache_has_all_public_static_methods(): void
    {
        $expectedMethods = [
            'register', 'set', 'get', 'forget', 'flush', 'count',
            'warm', 'registerWarm', 'warmCallbacks', 'invalidateOn',
            'isAvailable', 'registrations', 'octaneTableConfig', 'reset',
            'useResolver', 'useClock',
        ];

        foreach ($expectedMethods as $method) {
            $ref = new ReflectionMethod(SwooleCache::class, $method);
            $this->assertTrue($ref->isStatic(), "SwooleCache::{$method} should be static");
            $this->assertTrue($ref->isPublic(), "SwooleCache::{$method} should be public");
        }
    }

    public function test_swoole_cache_register_method_has_correct_params(): void
    {
        $ref = new ReflectionMethod(SwooleCache::class, 'register');
        $params = $ref->getParameters();

        $this->assertSame('name', $params[0]->getName());
        $this->assertSame('rows', $params[1]->getName());
        $this->assertSame('ttl', $params[2]->getName());
        $this->assertSame('valueSize', $params[3]->getName());
    }

    public function test_swoole_cache_octane_table_config_format(): void
    {
        SwooleCache::reset();
        SwooleCache::register('test_struct', rows: 50, ttl: 30, valueSize: 1000);

        $config = SwooleCache::octaneTableConfig();

        $this->assertArrayHasKey('test_struct:50', $config);
        $this->assertSame('string:1000', $config['test_struct:50']['value']);
        $this->assertSame('int', $config['test_struct:50']['expires_at']);

        SwooleCache::reset();
    }

    public function test_swoole_cache_multiple_registrations_produce_correct_config(): void
    {
        SwooleCache::reset();
        SwooleCache::register('table_a', rows: 100, ttl: 60, valueSize: 2000);
        SwooleCache::register('table_b', rows: 200, ttl: 120, valueSize: 4000);

        $config = SwooleCache::octaneTableConfig();

        $this->assertCount(2, $config);
        $this->assertArrayHasKey('table_a:100', $config);
        $this->assertArrayHasKey('table_b:200', $config);

        SwooleCache::reset();
    }

    // ========================================================================
    // SwooleTimer — structural coverage
    // ========================================================================

    public function test_swoole_timer_has_every_static_method(): void
    {
        $ref = new ReflectionMethod(SwooleTimer::class, 'every');

        $this->assertTrue($ref->isStatic());
        $this->assertTrue($ref->isPublic());
        /** @phpstan-ignore-next-line */
        $this->assertSame('bool', $ref->getReturnType()->getName());
    }

    public function test_swoole_timer_has_after_static_method(): void
    {
        $ref = new ReflectionMethod(SwooleTimer::class, 'after');

        $this->assertTrue($ref->isStatic());
        $this->assertTrue($ref->isPublic());
        /** @phpstan-ignore-next-line */
        $this->assertSame('bool', $ref->getReturnType()->getName());
    }

    public function test_swoole_timer_has_cancel_static_method(): void
    {
        $ref = new ReflectionMethod(SwooleTimer::class, 'cancel');

        $this->assertTrue($ref->isStatic());
        $this->assertTrue($ref->isPublic());
        /** @phpstan-ignore-next-line */
        $this->assertSame('bool', $ref->getReturnType()->getName());
    }

    public function test_swoole_timer_has_list_static_method(): void
    {
        $ref = new ReflectionMethod(SwooleTimer::class, 'list');

        $this->assertTrue($ref->isStatic());
        $this->assertTrue($ref->isPublic());
        /** @phpstan-ignore-next-line */
        $this->assertSame('array', $ref->getReturnType()->getName());
    }

    public function test_swoole_timer_has_exists_static_method(): void
    {
        $ref = new ReflectionMethod(SwooleTimer::class, 'exists');

        $this->assertTrue($ref->isStatic());
        $this->assertTrue($ref->isPublic());
        /** @phpstan-ignore-next-line */
        $this->assertSame('bool', $ref->getReturnType()->getName());
    }

    public function test_swoole_timer_has_restore_static_method(): void
    {
        $ref = new ReflectionMethod(SwooleTimer::class, 'restore');

        $this->assertTrue($ref->isStatic());
        $this->assertTrue($ref->isPublic());
    }

    public function test_swoole_timer_has_testing_helpers(): void
    {
        $methods = ['useRedis', 'setAvailable', 'useDispatcher', 'timerIds', 'reset'];

        foreach ($methods as $method) {
            $ref = new ReflectionMethod(SwooleTimer::class, $method);
            $this->assertTrue($ref->isStatic(), "SwooleTimer::{$method} should be static");
            $this->assertTrue($ref->isPublic(), "SwooleTimer::{$method} should be public");
        }
    }

    public function test_swoole_timer_every_param_signature(): void
    {
        $ref = new ReflectionMethod(SwooleTimer::class, 'every');
        $params = $ref->getParameters();

        $this->assertSame('key', $params[0]->getName());
        $this->assertSame('seconds', $params[1]->getName());
        $this->assertSame('job', $params[2]->getName());
        $this->assertSame('data', $params[3]->getName());
        $this->assertTrue($params[3]->isDefaultValueAvailable());
    }

    // ========================================================================
    // NotificationBadgeCacheManager — structural coverage
    // ========================================================================

    public function test_notification_badge_has_register_method(): void
    {
        $ref = new ReflectionMethod(NotificationBadgeCacheManager::class, 'register');

        $this->assertTrue($ref->isStatic());
        $this->assertTrue($ref->isPublic());
    }

    public function test_notification_badge_has_get_badge_method(): void
    {
        $ref = new ReflectionMethod(NotificationBadgeCacheManager::class, 'getBadge');
        $params = $ref->getParameters();

        $this->assertTrue($ref->isStatic());
        $this->assertTrue($ref->isPublic());
        $this->assertCount(1, $params);
        $this->assertSame('userId', $params[0]->getName());
        $this->assertTrue($params[0]->allowsNull());
    }

    public function test_notification_badge_has_compute_unread_count_method(): void
    {
        $ref = new ReflectionMethod(NotificationBadgeCacheManager::class, 'computeUnreadCount');

        $this->assertTrue($ref->isStatic());
        $this->assertTrue($ref->isPublic());
    }

    public function test_notification_badge_has_invalidate_for_user_method(): void
    {
        $ref = new ReflectionMethod(NotificationBadgeCacheManager::class, 'invalidateForUser');

        $this->assertTrue($ref->isStatic());
        $this->assertTrue($ref->isPublic());
    }

    // ========================================================================
    // PermissionCacheManager — structural coverage
    // ========================================================================

    public function test_permission_cache_has_register_method(): void
    {
        $ref = new ReflectionMethod(PermissionCacheManager::class, 'register');

        $this->assertTrue($ref->isStatic());
        $this->assertTrue($ref->isPublic());
    }

    public function test_permission_cache_has_build_cache_for_user_method(): void
    {
        $ref = new ReflectionMethod(PermissionCacheManager::class, 'buildCacheForUser');

        $this->assertTrue($ref->isStatic());
        $this->assertTrue($ref->isPublic());
    }

    public function test_permission_cache_constants(): void
    {
        $this->assertSame('permissions', PermissionCacheManager::TABLE_NAME);
        $this->assertSame(2000, PermissionCacheManager::TABLE_ROWS);
        $this->assertSame(300, PermissionCacheManager::TABLE_TTL);
        $this->assertSame(5000, PermissionCacheManager::TABLE_VALUE_SIZE);
    }

    // ========================================================================
    // ServiceHealthCacheManager — structural coverage
    // ========================================================================

    public function test_service_health_has_register_method(): void
    {
        $ref = new ReflectionMethod(ServiceHealthCacheManager::class, 'register');

        $this->assertTrue($ref->isStatic());
        $this->assertTrue($ref->isPublic());
    }

    public function test_service_health_has_get_cached_availability_method(): void
    {
        $ref = new ReflectionMethod(ServiceHealthCacheManager::class, 'getCachedAvailability');
        $params = $ref->getParameters();

        $this->assertTrue($ref->isStatic());
        $this->assertTrue($ref->isPublic());
        $this->assertCount(1, $params);
        $this->assertSame('service', $params[0]->getName());
    }

    public function test_service_health_has_store_availability_method(): void
    {
        $ref = new ReflectionMethod(ServiceHealthCacheManager::class, 'storeAvailability');
        $params = $ref->getParameters();

        $this->assertTrue($ref->isStatic());
        $this->assertTrue($ref->isPublic());
        $this->assertCount(2, $params);
        $this->assertSame('service', $params[0]->getName());
        $this->assertSame('available', $params[1]->getName());
    }

    public function test_service_health_has_invalidate_method(): void
    {
        $ref = new ReflectionMethod(ServiceHealthCacheManager::class, 'invalidate');

        $this->assertTrue($ref->isStatic());
        $this->assertTrue($ref->isPublic());
    }

    public function test_service_health_constants(): void
    {
        $this->assertSame('service_health', ServiceHealthCacheManager::TABLE_NAME);
        $this->assertSame(10, ServiceHealthCacheManager::TABLE_ROWS);
        $this->assertSame(30, ServiceHealthCacheManager::TABLE_TTL);
        $this->assertSame(200, ServiceHealthCacheManager::TABLE_VALUE_SIZE);
    }

    // ========================================================================
    // RestoreSwooleTimers Listener — structural coverage
    // ========================================================================

    public function test_restore_swoole_timers_has_handle_method(): void
    {
        $ref = new ReflectionMethod(RestoreSwooleTimers::class, 'handle');

        $this->assertTrue($ref->isPublic());
        $this->assertFalse($ref->isStatic());
    }

    public function test_restore_swoole_timers_handle_accepts_worker_starting_event(): void
    {
        $ref = new ReflectionMethod(RestoreSwooleTimers::class, 'handle');
        $params = $ref->getParameters();

        $this->assertCount(1, $params);
        /** @phpstan-ignore-next-line */
        $this->assertSame(WorkerStarting::class, $params[0]->getType()->getName());
    }

    public function test_restore_swoole_timers_is_instantiable(): void
    {
        $listener = new RestoreSwooleTimers;

        $this->assertInstanceOf(RestoreSwooleTimers::class, $listener);
    }

    // ========================================================================
    // WarmSwooleCaches Listener — structural coverage
    // ========================================================================

    public function test_warm_swoole_caches_has_handle_method(): void
    {
        $ref = new ReflectionMethod(WarmSwooleCaches::class, 'handle');

        $this->assertTrue($ref->isPublic());
        $this->assertFalse($ref->isStatic());
    }

    public function test_warm_swoole_caches_handle_accepts_worker_starting_event(): void
    {
        $ref = new ReflectionMethod(WarmSwooleCaches::class, 'handle');
        $params = $ref->getParameters();

        $this->assertCount(1, $params);
        /** @phpstan-ignore-next-line */
        $this->assertSame(WorkerStarting::class, $params[0]->getType()->getName());
    }

    public function test_warm_swoole_caches_is_instantiable(): void
    {
        $listener = new WarmSwooleCaches;

        $this->assertInstanceOf(WarmSwooleCaches::class, $listener);
    }

    // ========================================================================
    // All cache managers have consistent TABLE_NAME constant
    // ========================================================================

    public function test_all_cache_managers_define_table_name_constant(): void
    {
        $managers = [
            NotificationBadgeCacheManager::class => 'notification_badges',
            PermissionCacheManager::class => 'permissions',
            ServiceHealthCacheManager::class => 'service_health',
        ];

        foreach ($managers as $class => $expectedTableName) {
            $this->assertSame(
                $expectedTableName,
                constant("{$class}::TABLE_NAME"),
                "{$class} TABLE_NAME should be {$expectedTableName}"
            );
        }
    }

    public function test_all_cache_managers_define_table_rows_constant(): void
    {
        $managers = [
            NotificationBadgeCacheManager::class,
            PermissionCacheManager::class,
            ServiceHealthCacheManager::class,
        ];

        foreach ($managers as $class) {
            $this->assertIsInt(
                constant("{$class}::TABLE_ROWS"),
                "{$class} should define TABLE_ROWS as int"
            );
            $this->assertGreaterThan(
                0,
                constant("{$class}::TABLE_ROWS"),
                "{$class}::TABLE_ROWS should be positive"
            );
        }
    }

    public function test_all_cache_managers_define_table_ttl_constant(): void
    {
        $managers = [
            NotificationBadgeCacheManager::class,
            PermissionCacheManager::class,
            ServiceHealthCacheManager::class,
        ];

        foreach ($managers as $class) {
            $this->assertIsInt(
                constant("{$class}::TABLE_TTL"),
                "{$class} should define TABLE_TTL as int"
            );
            $this->assertGreaterThan(
                0,
                constant("{$class}::TABLE_TTL"),
                "{$class}::TABLE_TTL should be positive"
            );
        }
    }

    public function test_all_cache_managers_define_table_value_size_constant(): void
    {
        $managers = [
            NotificationBadgeCacheManager::class,
            PermissionCacheManager::class,
            ServiceHealthCacheManager::class,
        ];

        foreach ($managers as $class) {
            $this->assertIsInt(
                constant("{$class}::TABLE_VALUE_SIZE"),
                "{$class} should define TABLE_VALUE_SIZE as int"
            );
            $this->assertGreaterThan(
                0,
                constant("{$class}::TABLE_VALUE_SIZE"),
                "{$class}::TABLE_VALUE_SIZE should be positive"
            );
        }
    }

    public function test_all_cache_managers_have_static_register_method(): void
    {
        $managers = [
            NotificationBadgeCacheManager::class,
            PermissionCacheManager::class,
            ServiceHealthCacheManager::class,
        ];

        foreach ($managers as $class) {
            $ref = new ReflectionMethod($class, 'register');
            $this->assertTrue($ref->isStatic(), "{$class}::register should be static");
            $this->assertTrue($ref->isPublic(), "{$class}::register should be public");
        }
    }
}
