<?php

namespace Aicl\Tests\Unit\Swoole;

use Aicl\Swoole\SwooleCache;
use Carbon\Carbon;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;
use Tests\TestCase;

class SwooleCacheTest extends TestCase
{
    /** @var array<string, array<string, array<string, mixed>>> */
    private array $tables = [];

    protected function setUp(): void
    {
        parent::setUp();

        SwooleCache::reset();

        // Register a test table
        SwooleCache::register('test_cache', rows: 100, ttl: 60, valueSize: 5000);

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
    }

    protected function tearDown(): void
    {
        SwooleCache::reset();
        $this->tables = [];

        parent::tearDown();
    }

    // -- Registration --

    public function test_register_stores_table_definition(): void
    {
        SwooleCache::register('permissions', rows: 500, ttl: 30, valueSize: 2000);

        $registrations = SwooleCache::registrations();

        $this->assertArrayHasKey('permissions', $registrations);
        $this->assertSame(500, $registrations['permissions']['rows']);
        $this->assertSame(30, $registrations['permissions']['ttl']);
        $this->assertSame(2000, $registrations['permissions']['valueSize']);
    }

    public function test_register_uses_defaults(): void
    {
        SwooleCache::register('simple');

        $reg = SwooleCache::registrations()['simple'];

        $this->assertSame(1000, $reg['rows']);
        $this->assertSame(60, $reg['ttl']);
        $this->assertSame(10000, $reg['valueSize']);
    }

    public function test_octane_table_config_generates_correct_format(): void
    {
        SwooleCache::register('permissions', rows: 500, ttl: 30, valueSize: 2000);

        $config = SwooleCache::octaneTableConfig();

        $this->assertArrayHasKey('test_cache:100', $config);
        $this->assertArrayHasKey('permissions:500', $config);
        $this->assertSame('string:2000', $config['permissions:500']['value']);
        $this->assertSame('int', $config['permissions:500']['expires_at']);
    }

    // -- Set / Get round-trip --

    public function test_set_and_get_string_value(): void
    {
        $this->assertTrue(SwooleCache::set('test_cache', 'key1', 'hello'));
        $this->assertSame('hello', SwooleCache::get('test_cache', 'key1'));
    }

    public function test_set_and_get_array_value(): void
    {
        $data = ['name' => 'John', 'roles' => ['admin', 'editor']];

        SwooleCache::set('test_cache', 'user:1', $data);

        $this->assertSame($data, SwooleCache::get('test_cache', 'user:1'));
    }

    public function test_set_and_get_integer_value(): void
    {
        SwooleCache::set('test_cache', 'count', 42);

        $this->assertSame(42, SwooleCache::get('test_cache', 'count'));
    }

    public function test_set_and_get_null_value(): void
    {
        SwooleCache::set('test_cache', 'nullable', null);

        // null stored as JSON "null" — get() returns null which is indistinguishable from miss
        // This is by design — callers should avoid storing null
        $this->assertNull(SwooleCache::get('test_cache', 'nullable'));
    }

    public function test_set_and_get_boolean_value(): void
    {
        SwooleCache::set('test_cache', 'flag', true);

        $this->assertTrue(SwooleCache::get('test_cache', 'flag'));
    }

    public function test_get_returns_null_for_missing_key(): void
    {
        $this->assertNull(SwooleCache::get('test_cache', 'nonexistent'));
    }

    public function test_get_throws_for_unregistered_table(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('SwooleCache table [unknown] has not been registered.');

        SwooleCache::get('unknown', 'key');
    }

    public function test_set_throws_for_unregistered_table(): void
    {
        $this->expectException(InvalidArgumentException::class);

        SwooleCache::set('unknown', 'key', 'value');
    }

    // -- TTL --

    public function test_expired_row_returns_null(): void
    {
        SwooleCache::set('test_cache', 'short', 'data', ttl: 1);

        // Not expired yet
        $this->assertSame('data', SwooleCache::get('test_cache', 'short'));

        // Travel forward in time past TTL
        Carbon::setTestNow(Carbon::now()->addSeconds(2));

        $this->assertNull(SwooleCache::get('test_cache', 'short'));

        Carbon::setTestNow(); // Reset
    }

    public function test_expired_row_is_lazily_deleted(): void
    {
        SwooleCache::set('test_cache', 'expiring', 'data', ttl: 1);

        $this->assertSame(1, SwooleCache::count('test_cache'));

        Carbon::setTestNow(Carbon::now()->addSeconds(2));

        // Get triggers lazy delete
        SwooleCache::get('test_cache', 'expiring');

        $this->assertSame(0, SwooleCache::count('test_cache'));

        Carbon::setTestNow();
    }

    public function test_custom_ttl_overrides_table_default(): void
    {
        // Table default is 60s, use custom 1s
        SwooleCache::set('test_cache', 'custom_ttl', 'data', ttl: 1);

        Carbon::setTestNow(Carbon::now()->addSeconds(2));

        $this->assertNull(SwooleCache::get('test_cache', 'custom_ttl'));

        Carbon::setTestNow();
    }

    public function test_non_expired_row_is_returned(): void
    {
        SwooleCache::set('test_cache', 'fresh', 'data', ttl: 120);

        Carbon::setTestNow(Carbon::now()->addSeconds(60));

        $this->assertSame('data', SwooleCache::get('test_cache', 'fresh'));

        Carbon::setTestNow();
    }

    // -- Forget --

    public function test_forget_removes_row(): void
    {
        SwooleCache::set('test_cache', 'key1', 'data');

        $this->assertTrue(SwooleCache::forget('test_cache', 'key1'));
        $this->assertNull(SwooleCache::get('test_cache', 'key1'));
    }

    public function test_forget_returns_false_for_missing_key(): void
    {
        // Our mock returns false for missing keys
        $this->assertFalse(SwooleCache::forget('test_cache', 'nonexistent'));
    }

    // -- Flush --

    public function test_flush_clears_all_rows(): void
    {
        SwooleCache::set('test_cache', 'a', 1);
        SwooleCache::set('test_cache', 'b', 2);
        SwooleCache::set('test_cache', 'c', 3);

        $this->assertSame(3, SwooleCache::count('test_cache'));

        $this->assertTrue(SwooleCache::flush('test_cache'));

        $this->assertSame(0, SwooleCache::count('test_cache'));
    }

    // -- Count --

    public function test_count_returns_row_count(): void
    {
        $this->assertSame(0, SwooleCache::count('test_cache'));

        SwooleCache::set('test_cache', 'a', 1);
        SwooleCache::set('test_cache', 'b', 2);

        $this->assertSame(2, SwooleCache::count('test_cache'));
    }

    // -- Warm --

    public function test_warm_populates_table_from_loader(): void
    {
        SwooleCache::warm('test_cache', fn (): array => [
            'config:site_name' => 'AICL',
            'config:timezone' => 'UTC',
        ]);

        $this->assertSame('AICL', SwooleCache::get('test_cache', 'config:site_name'));
        $this->assertSame('UTC', SwooleCache::get('test_cache', 'config:timezone'));
        $this->assertSame(2, SwooleCache::count('test_cache'));
    }

    public function test_register_warm_stores_callback(): void
    {
        $loader = fn (): array => ['key' => 'value'];

        SwooleCache::registerWarm('test_cache', $loader);

        $callbacks = SwooleCache::warmCallbacks();

        $this->assertArrayHasKey('test_cache', $callbacks);
        $this->assertCount(1, $callbacks['test_cache']);
    }

    public function test_register_warm_supports_multiple_loaders(): void
    {
        SwooleCache::registerWarm('test_cache', fn (): array => ['a' => 1]);
        SwooleCache::registerWarm('test_cache', fn (): array => ['b' => 2]);

        $this->assertCount(2, SwooleCache::warmCallbacks()['test_cache']);
    }

    // -- Invalidation --

    public function test_invalidate_on_registers_event_listener(): void
    {
        Event::fake();

        SwooleCache::set('test_cache', 'user:1', ['perms' => ['admin']]);

        SwooleCache::invalidateOn(
            'test_cache',
            TestPermissionUpdated::class,
            fn (TestPermissionUpdated $e): string => "user:{$e->userId}",
        );

        // Dispatch event — this should trigger forget
        Event::assertListening(TestPermissionUpdated::class, \Closure::class);
    }

    public function test_invalidate_on_actually_forgets_key(): void
    {
        SwooleCache::set('test_cache', 'user:1', ['perms' => ['admin']]);

        SwooleCache::invalidateOn(
            'test_cache',
            TestPermissionUpdated::class,
            fn (TestPermissionUpdated $e): string => "user:{$e->userId}",
        );

        // Fire real event
        event(new TestPermissionUpdated(1));

        $this->assertNull(SwooleCache::get('test_cache', 'user:1'));
    }

    public function test_invalidate_on_supports_multiple_keys(): void
    {
        SwooleCache::set('test_cache', 'user:1', 'data1');
        SwooleCache::set('test_cache', 'user:2', 'data2');

        SwooleCache::invalidateOn(
            'test_cache',
            TestBulkInvalidation::class,
            fn (TestBulkInvalidation $e): array => $e->keys,
        );

        event(new TestBulkInvalidation(['user:1', 'user:2']));

        $this->assertNull(SwooleCache::get('test_cache', 'user:1'));
        $this->assertNull(SwooleCache::get('test_cache', 'user:2'));
    }

    // -- isAvailable --

    public function test_is_available_returns_true_when_resolver_set(): void
    {
        $this->assertTrue(SwooleCache::isAvailable());
    }

    public function test_is_available_returns_false_after_reset(): void
    {
        SwooleCache::reset();
        SwooleCache::register('test_cache');

        // No resolver, no Swoole
        $this->assertFalse(SwooleCache::isAvailable());
    }

    // -- Reset --

    public function test_reset_clears_all_state(): void
    {
        SwooleCache::register('extra');
        SwooleCache::registerWarm('test_cache', fn (): array => []);

        SwooleCache::reset();

        $this->assertEmpty(SwooleCache::registrations());
        $this->assertEmpty(SwooleCache::warmCallbacks());
    }

    // -- Fallback behavior --

    public function test_set_returns_false_when_unavailable(): void
    {
        SwooleCache::reset();
        SwooleCache::register('test_cache');

        $this->assertFalse(SwooleCache::set('test_cache', 'key', 'val'));
    }

    public function test_get_returns_null_when_unavailable(): void
    {
        SwooleCache::reset();
        SwooleCache::register('test_cache');

        $this->assertNull(SwooleCache::get('test_cache', 'key'));
    }

    public function test_forget_returns_false_when_unavailable(): void
    {
        SwooleCache::reset();
        SwooleCache::register('test_cache');

        $this->assertFalse(SwooleCache::forget('test_cache', 'key'));
    }

    public function test_flush_returns_false_when_unavailable(): void
    {
        SwooleCache::reset();
        SwooleCache::register('test_cache');

        $this->assertFalse(SwooleCache::flush('test_cache'));
    }

    public function test_count_returns_zero_when_unavailable(): void
    {
        SwooleCache::reset();
        SwooleCache::register('test_cache');

        $this->assertSame(0, SwooleCache::count('test_cache'));
    }

    // -- Edge cases --

    public function test_set_overwrites_existing_key(): void
    {
        SwooleCache::set('test_cache', 'key', 'first');
        SwooleCache::set('test_cache', 'key', 'second');

        $this->assertSame('second', SwooleCache::get('test_cache', 'key'));
        $this->assertSame(1, SwooleCache::count('test_cache'));
    }

    public function test_empty_string_value(): void
    {
        SwooleCache::set('test_cache', 'empty', '');

        $this->assertSame('', SwooleCache::get('test_cache', 'empty'));
    }

    public function test_nested_array_value(): void
    {
        $data = ['level1' => ['level2' => ['level3' => 'deep']]];

        SwooleCache::set('test_cache', 'nested', $data);

        $this->assertSame($data, SwooleCache::get('test_cache', 'nested'));
    }

    /**
     * Create a mock Swoole Table backed by an in-memory array.
     *
     * This simulates the Swoole Table interface without requiring the extension.
     */
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

/**
 * Test event class for invalidation testing.
 */
class TestPermissionUpdated
{
    public function __construct(public int $userId) {}
}

/**
 * Test event class for bulk invalidation testing.
 */
class TestBulkInvalidation
{
    /** @param  list<string>  $keys */
    public function __construct(public array $keys) {}
}
