<?php

namespace Aicl\Tests\Unit\Swoole;

use Aicl\Swoole\Cache\ServiceHealthCacheManager;
use Aicl\Swoole\SwooleCache;
use Carbon\Carbon;
use Tests\TestCase;

class ServiceHealthCacheManagerTest extends TestCase
{
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

        ServiceHealthCacheManager::register();
    }

    protected function tearDown(): void
    {
        SwooleCache::reset();
        $this->tables = [];
        Carbon::setTestNow();

        parent::tearDown();
    }

    // -- Constants --

    public function test_table_name_is_service_health(): void
    {
        $this->assertSame('service_health', ServiceHealthCacheManager::TABLE_NAME);
    }

    public function test_table_rows_is_10(): void
    {
        $this->assertSame(10, ServiceHealthCacheManager::TABLE_ROWS);
    }

    public function test_table_ttl_is_30(): void
    {
        $this->assertSame(30, ServiceHealthCacheManager::TABLE_TTL);
    }

    public function test_table_value_size_is_200(): void
    {
        $this->assertSame(200, ServiceHealthCacheManager::TABLE_VALUE_SIZE);
    }

    // -- Registration --

    public function test_register_creates_swoole_cache_table(): void
    {
        $registrations = SwooleCache::registrations();

        $this->assertArrayHasKey('service_health', $registrations);
        $this->assertSame(10, $registrations['service_health']['rows']);
        $this->assertSame(30, $registrations['service_health']['ttl']);
        $this->assertSame(200, $registrations['service_health']['valueSize']);
    }

    // -- getCachedAvailability --

    public function test_get_cached_availability_returns_null_on_miss(): void
    {
        $this->assertNull(ServiceHealthCacheManager::getCachedAvailability('elasticsearch'));
    }

    public function test_get_cached_availability_returns_true_when_stored_as_available(): void
    {
        ServiceHealthCacheManager::storeAvailability('elasticsearch', true);

        $this->assertTrue(ServiceHealthCacheManager::getCachedAvailability('elasticsearch'));
    }

    public function test_get_cached_availability_returns_false_when_stored_as_unavailable(): void
    {
        ServiceHealthCacheManager::storeAvailability('elasticsearch', false);

        $this->assertFalse(ServiceHealthCacheManager::getCachedAvailability('elasticsearch'));
    }

    public function test_get_cached_availability_returns_null_when_swoole_unavailable(): void
    {
        SwooleCache::reset();
        SwooleCache::register('service_health', rows: 10, ttl: 30, valueSize: 200);

        $this->assertFalse(SwooleCache::isAvailable());
        $this->assertNull(ServiceHealthCacheManager::getCachedAvailability('elasticsearch'));

        // Re-set resolver for tearDown
        SwooleCache::useResolver(function (string $table): ?object {
            if (! isset($this->tables[$table])) {
                $this->tables[$table] = [];
            }

            return $this->createMockTable($table);
        });
    }

    // -- storeAvailability --

    public function test_store_availability_does_not_throw_when_swoole_unavailable(): void
    {
        SwooleCache::reset();
        SwooleCache::register('service_health', rows: 10, ttl: 30, valueSize: 200);

        // Should silently no-op
        ServiceHealthCacheManager::storeAvailability('elasticsearch', true);

        $this->assertFalse(SwooleCache::isAvailable());

        // Re-set resolver for tearDown
        SwooleCache::useResolver(function (string $table): ?object {
            if (! isset($this->tables[$table])) {
                $this->tables[$table] = [];
            }

            return $this->createMockTable($table);
        });
    }

    // -- invalidate --

    public function test_invalidate_clears_cached_availability(): void
    {
        ServiceHealthCacheManager::storeAvailability('elasticsearch', true);
        $this->assertTrue(ServiceHealthCacheManager::getCachedAvailability('elasticsearch'));

        ServiceHealthCacheManager::invalidate('elasticsearch');

        $this->assertNull(ServiceHealthCacheManager::getCachedAvailability('elasticsearch'));
    }

    // -- TTL --

    public function test_cached_availability_expires_after_ttl(): void
    {
        ServiceHealthCacheManager::storeAvailability('elasticsearch', true);
        $this->assertTrue(ServiceHealthCacheManager::getCachedAvailability('elasticsearch'));

        // Advance past 30s TTL
        Carbon::setTestNow(now()->addSeconds(31));

        $this->assertNull(ServiceHealthCacheManager::getCachedAvailability('elasticsearch'));
    }

    // -- Multiple services --

    public function test_stores_independent_service_health(): void
    {
        ServiceHealthCacheManager::storeAvailability('elasticsearch', true);
        ServiceHealthCacheManager::storeAvailability('redis', false);

        $this->assertTrue(ServiceHealthCacheManager::getCachedAvailability('elasticsearch'));
        $this->assertFalse(ServiceHealthCacheManager::getCachedAvailability('redis'));
    }

    public function test_invalidate_only_affects_specified_service(): void
    {
        ServiceHealthCacheManager::storeAvailability('elasticsearch', true);
        ServiceHealthCacheManager::storeAvailability('redis', true);

        ServiceHealthCacheManager::invalidate('elasticsearch');

        $this->assertNull(ServiceHealthCacheManager::getCachedAvailability('elasticsearch'));
        $this->assertTrue(ServiceHealthCacheManager::getCachedAvailability('redis'));
    }

    // -- Helpers --

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
