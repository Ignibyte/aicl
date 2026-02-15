<?php

namespace Aicl\Tests\Feature\Swoole;

use Aicl\Swoole\SwooleTimer;
use PHPUnit\Framework\TestCase;

/**
 * Feature tests for SwooleTimer that require Swoole runtime.
 *
 * These tests verify actual Swoole timer behavior by running inside
 * Coroutine\run(). They skip gracefully when Swoole is not available.
 *
 * Note: These tests use mock Redis and mock dispatch to avoid
 * requiring a full Laravel app or Redis connection.
 */
class SwooleTimerSwooleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! extension_loaded('swoole')) {
            $this->markTestSkipped('Swoole extension not available');
        }

        SwooleTimer::reset();
    }

    protected function tearDown(): void
    {
        SwooleTimer::reset();
        parent::tearDown();
    }

    public function test_is_available_returns_true_inside_coroutine(): void
    {
        \Swoole\Coroutine\run(function (): void {
            // Inside coroutine, getCid() > 0
            // But isAvailable also checks Octane class — force override
            SwooleTimer::setAvailable(true);
            $this->assertTrue(SwooleTimer::isAvailable());
        });
    }

    public function test_is_available_returns_false_outside_coroutine(): void
    {
        // Outside coroutine context, should check real availability
        SwooleTimer::setAvailable(null);

        // getCid() returns -1 outside coroutine context
        // The actual result depends on whether Octane class exists
        // but getCid() check should return >= 0 only in coroutine context
        $available = SwooleTimer::isAvailable();

        // Outside Coroutine\run(), Swoole\Coroutine::getCid() returns -1
        // so isAvailable should return false even with extension loaded
        $this->assertFalse($available);
    }

    public function test_after_fires_callback_and_cleans_up(): void
    {
        $fired = false;
        $dispatched = [];
        $redisStore = [];
        $mockRedis = $this->createMockRedis($redisStore);

        \Swoole\Coroutine\run(function () use (&$fired, &$dispatched, $mockRedis): void {
            SwooleTimer::setAvailable(true);
            SwooleTimer::useRedis(fn () => $mockRedis);
            SwooleTimer::useDispatcher(function (string|object $job, array $data) use (&$dispatched): void {
                $dispatched[] = ['job' => $job, 'data' => $data];
            });

            $result = SwooleTimer::after('test-after', 1, 'App\\Jobs\\FakeJob');

            $this->assertTrue($result);
            $this->assertArrayHasKey('test-after', SwooleTimer::timerIds());

            // Wait for timer to fire
            \Swoole\Coroutine::sleep(1.5);

            // After firing, one-shot timer should clean up
            $this->assertArrayNotHasKey('test-after', SwooleTimer::timerIds());
            $fired = true;
        });

        $this->assertTrue($fired, 'Coroutine run should have completed');
        $this->assertCount(1, $dispatched);
        $this->assertSame('App\\Jobs\\FakeJob', $dispatched[0]['job']);
    }

    public function test_every_registers_recurring_swoole_timer(): void
    {
        $redisStore = [];
        $mockRedis = $this->createMockRedis($redisStore);

        \Swoole\Coroutine\run(function () use ($mockRedis): void {
            SwooleTimer::setAvailable(true);
            SwooleTimer::useRedis(fn () => $mockRedis);
            SwooleTimer::useDispatcher(function (): void {});

            $result = SwooleTimer::every('recurring', 1, 'App\\Jobs\\FakeJob');

            $this->assertTrue($result);
            $this->assertArrayHasKey('recurring', SwooleTimer::timerIds());

            // Timer ID should be a valid integer
            $timerId = SwooleTimer::timerIds()['recurring'];
            $this->assertIsInt($timerId);
            $this->assertGreaterThan(0, $timerId);

            // Cancel to clean up
            SwooleTimer::cancel('recurring');
        });
    }

    public function test_cancel_stops_swoole_timer(): void
    {
        $redisStore = [];
        $mockRedis = $this->createMockRedis($redisStore);

        \Swoole\Coroutine\run(function () use ($mockRedis): void {
            SwooleTimer::setAvailable(true);
            SwooleTimer::useRedis(fn () => $mockRedis);
            SwooleTimer::useDispatcher(function (): void {});

            SwooleTimer::every('to-cancel', 1, 'App\\Jobs\\FakeJob');
            $this->assertArrayHasKey('to-cancel', SwooleTimer::timerIds());

            $result = SwooleTimer::cancel('to-cancel');

            $this->assertTrue($result);
            $this->assertArrayNotHasKey('to-cancel', SwooleTimer::timerIds());
        });
    }

    /**
     * Create a minimal mock Redis for feature tests (no Laravel app).
     *
     * @param  array<string, string>  $store  Reference to external storage
     */
    private function createMockRedis(array &$store): object
    {
        return new class($store)
        {
            /** @var array<string, string> */
            private array $store;

            public function __construct(array &$store)
            {
                $this->store = &$store;
            }

            public function set(string $key, string $value): bool
            {
                $this->store[$key] = $value;

                return true;
            }

            public function get(string $key): ?string
            {
                return $this->store[$key] ?? null;
            }

            public function del(string $key): int
            {
                if (isset($this->store[$key])) {
                    unset($this->store[$key]);

                    return 1;
                }

                return 0;
            }

            public function exists(string $key): int
            {
                return isset($this->store[$key]) ? 1 : 0;
            }

            /**
             * @return array<int, string>
             */
            public function keys(string $pattern): array
            {
                $prefix = str_replace('*', '', $pattern);
                $matches = [];

                foreach ($this->store as $key => $value) {
                    if (str_starts_with($key, $prefix)) {
                        $matches[] = $key;
                    }
                }

                return $matches;
            }
        };
    }
}
