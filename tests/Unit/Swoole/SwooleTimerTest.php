<?php

namespace Aicl\Tests\Unit\Swoole;

use Aicl\Swoole\SwooleTimer;
use Tests\TestCase;

class SwooleTimerTest extends TestCase
{
    /**
     * Mock Redis storage for timer definitions.
     *
     * @var array<string, string>
     */
    private array $redisStore = [];

    private object $mockRedis;

    protected function setUp(): void
    {
        parent::setUp();

        $this->redisStore = [];
        $this->mockRedis = $this->createMockRedis();

        SwooleTimer::reset();
        SwooleTimer::setAvailable(false);
        SwooleTimer::useRedis(fn () => $this->mockRedis);
    }

    protected function tearDown(): void
    {
        SwooleTimer::reset();
        parent::tearDown();
    }

    // -- every() tests --

    public function test_every_stores_timer_definition_in_redis(): void
    {
        $result = SwooleTimer::every('cleanup', 300, 'App\\Jobs\\CleanupJob');

        $this->assertFalse($result, 'Should return false when Swoole unavailable');
        $this->assertArrayHasKey('aicl:timers:cleanup', $this->redisStore);

        $definition = json_decode($this->redisStore['aicl:timers:cleanup'], true);
        $this->assertSame('recurring', $definition['type']);
        $this->assertSame('cleanup', $definition['key']);
        $this->assertSame(300, $definition['seconds']);
        $this->assertSame('App\\Jobs\\CleanupJob', $definition['job']);
    }

    public function test_every_stores_data_in_definition(): void
    {
        SwooleTimer::every('report', 60, 'App\\Jobs\\ReportJob', ['daily', 'sales']);

        $definition = json_decode($this->redisStore['aicl:timers:report'], true);
        $this->assertSame(['daily', 'sales'], $definition['data']);
    }

    public function test_every_returns_false_when_swoole_unavailable(): void
    {
        $result = SwooleTimer::every('test', 10, 'App\\Jobs\\TestJob');

        $this->assertFalse($result);
    }

    public function test_every_stores_object_job_class_name(): void
    {
        $job = new \stdClass;
        SwooleTimer::every('obj-timer', 60, $job);

        $definition = json_decode($this->redisStore['aicl:timers:obj-timer'], true);
        $this->assertSame('stdClass', $definition['job']);
    }

    // -- after() tests --

    public function test_after_stores_timer_definition_in_redis(): void
    {
        $result = SwooleTimer::after('reminder', 600, 'App\\Jobs\\ReminderJob');

        $this->assertFalse($result, 'Should return false when Swoole unavailable');
        $this->assertArrayHasKey('aicl:timers:reminder', $this->redisStore);

        $definition = json_decode($this->redisStore['aicl:timers:reminder'], true);
        $this->assertSame('once', $definition['type']);
        $this->assertSame('reminder', $definition['key']);
        $this->assertSame(600, $definition['seconds']);
        $this->assertSame('App\\Jobs\\ReminderJob', $definition['job']);
    }

    public function test_after_returns_false_when_swoole_unavailable(): void
    {
        $result = SwooleTimer::after('test', 10, 'App\\Jobs\\TestJob');

        $this->assertFalse($result);
    }

    // -- cancel() tests --

    public function test_cancel_removes_timer_from_redis(): void
    {
        SwooleTimer::every('to-cancel', 60, 'App\\Jobs\\TestJob');
        $this->assertArrayHasKey('aicl:timers:to-cancel', $this->redisStore);

        $result = SwooleTimer::cancel('to-cancel');

        $this->assertTrue($result);
        $this->assertArrayNotHasKey('aicl:timers:to-cancel', $this->redisStore);
    }

    public function test_cancel_returns_false_when_timer_not_found(): void
    {
        $result = SwooleTimer::cancel('nonexistent');

        $this->assertFalse($result);
    }

    // -- list() tests --

    public function test_list_returns_all_active_timer_definitions(): void
    {
        SwooleTimer::every('recurring-1', 60, 'App\\Jobs\\Job1');
        SwooleTimer::after('oneshot-1', 300, 'App\\Jobs\\Job2');

        $list = SwooleTimer::list();

        $this->assertCount(2, $list);
        $this->assertArrayHasKey('recurring-1', $list);
        $this->assertArrayHasKey('oneshot-1', $list);

        $this->assertSame('recurring', $list['recurring-1']['type']);
        $this->assertSame(60, $list['recurring-1']['seconds']);
        $this->assertSame('App\\Jobs\\Job1', $list['recurring-1']['job']);

        $this->assertSame('once', $list['oneshot-1']['type']);
        $this->assertSame(300, $list['oneshot-1']['seconds']);
        $this->assertSame('App\\Jobs\\Job2', $list['oneshot-1']['job']);
    }

    public function test_list_returns_empty_array_when_no_timers(): void
    {
        $list = SwooleTimer::list();

        $this->assertSame([], $list);
    }

    // -- exists() tests --

    public function test_exists_returns_true_for_registered_timer(): void
    {
        SwooleTimer::every('check-me', 60, 'App\\Jobs\\TestJob');

        $this->assertTrue(SwooleTimer::exists('check-me'));
    }

    public function test_exists_returns_false_for_unknown_timer(): void
    {
        $this->assertFalse(SwooleTimer::exists('unknown'));
    }

    public function test_exists_returns_false_after_cancel(): void
    {
        SwooleTimer::every('temp', 60, 'App\\Jobs\\TestJob');
        SwooleTimer::cancel('temp');

        $this->assertFalse(SwooleTimer::exists('temp'));
    }

    // -- isAvailable() tests --

    public function test_is_available_returns_false_outside_swoole(): void
    {
        SwooleTimer::setAvailable(false);

        $this->assertFalse(SwooleTimer::isAvailable());
    }

    public function test_is_available_returns_true_when_overridden(): void
    {
        SwooleTimer::setAvailable(true);

        $this->assertTrue(SwooleTimer::isAvailable());
    }

    // -- reset() tests --

    public function test_reset_clears_all_state(): void
    {
        SwooleTimer::setAvailable(false);
        SwooleTimer::every('timer-1', 60, 'App\\Jobs\\Job1');
        SwooleTimer::every('timer-2', 120, 'App\\Jobs\\Job2');

        // Reset clears internal state (not Redis — that's intentional)
        SwooleTimer::reset();

        // After reset, availableOverride is null so isAvailable uses real check
        // Timer IDs are cleared
        $this->assertSame([], SwooleTimer::timerIds());
    }

    // -- registration_at tests --

    public function test_timer_definition_includes_registered_at_timestamp(): void
    {
        $before = time();
        SwooleTimer::every('timed', 60, 'App\\Jobs\\TestJob');
        $after = time();

        $definition = json_decode($this->redisStore['aicl:timers:timed'], true);

        $this->assertGreaterThanOrEqual($before, $definition['registered_at']);
        $this->assertLessThanOrEqual($after, $definition['registered_at']);
    }

    // -- overwriting existing timer --

    public function test_registering_same_key_overwrites_previous_definition(): void
    {
        SwooleTimer::every('overwrite', 60, 'App\\Jobs\\Job1');
        SwooleTimer::every('overwrite', 120, 'App\\Jobs\\Job2');

        $definition = json_decode($this->redisStore['aicl:timers:overwrite'], true);
        $this->assertSame(120, $definition['seconds']);
        $this->assertSame('App\\Jobs\\Job2', $definition['job']);
    }

    // -- Mock Redis --

    private function createMockRedis(): object
    {
        $store = &$this->redisStore;

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
