<?php

namespace Aicl\Tests\Feature\Swoole;

use Aicl\Models\GenerationTrace;
use Aicl\Models\RlmFailure;
use Aicl\Swoole\Cache\WidgetStatsCacheManager;
use Aicl\Swoole\SwooleCache;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WidgetStatsCacheFeatureTest extends TestCase
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
        SwooleCache::register('notification_badges', rows: 1000, ttl: 60, valueSize: 100);
        SwooleCache::register('permissions', rows: 2000, ttl: 300, valueSize: 5000);
        SwooleCache::register('rlm_stats', rows: 10, ttl: 300, valueSize: 5000);
        SwooleCache::register('service_health', rows: 10, ttl: 30, valueSize: 200);

        WidgetStatsCacheManager::register();
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
        \App\Models\User::factory()->create();

        // Create initial data
        RlmFailure::factory()->count(3)->create(['severity' => 'critical']);

        // First call — cache miss, computes from DB
        $data = WidgetStatsCacheManager::getOrCompute(
            'rlm_failure_stats',
            [WidgetStatsCacheManager::class, 'computeRlmFailureStats'],
        );

        $this->assertSame(3, $data['total']);
        $this->assertSame(3, $data['critical']);

        // Verify cache populated
        $cached = SwooleCache::get('widget_stats', 'rlm_failure_stats');
        $this->assertNotNull($cached);
        $this->assertSame(3, $cached['total']);

        // Second call — cache hit (same data returned)
        $data2 = WidgetStatsCacheManager::getOrCompute(
            'rlm_failure_stats',
            [WidgetStatsCacheManager::class, 'computeRlmFailureStats'],
        );
        $this->assertSame(3, $data2['total']);

        // Create new failure — triggers invalidation
        RlmFailure::factory()->create(['severity' => 'high']);

        // Cache should be invalidated
        $this->assertNull(SwooleCache::get('widget_stats', 'rlm_failure_stats'));

        // Third call — cache miss, recomputes with new data
        $data3 = WidgetStatsCacheManager::getOrCompute(
            'rlm_failure_stats',
            [WidgetStatsCacheManager::class, 'computeRlmFailureStats'],
        );

        $this->assertSame(4, $data3['total']);
        $this->assertSame(3, $data3['critical']);
        $this->assertSame(1, $data3['high']);
    }

    public function test_independent_widget_groups_dont_affect_each_other(): void
    {
        \App\Models\User::factory()->create();

        // Populate both caches
        WidgetStatsCacheManager::getOrCompute(
            'rlm_failure_stats',
            [WidgetStatsCacheManager::class, 'computeRlmFailureStats'],
        );
        WidgetStatsCacheManager::getOrCompute(
            'rlm_pattern_stats',
            [WidgetStatsCacheManager::class, 'computeRlmPatternStats'],
        );

        // Both should be cached
        $this->assertNotNull(SwooleCache::get('widget_stats', 'rlm_failure_stats'));
        $this->assertNotNull(SwooleCache::get('widget_stats', 'rlm_pattern_stats'));

        // Create a failure — only failure keys should be invalidated
        RlmFailure::factory()->create();

        $this->assertNull(SwooleCache::get('widget_stats', 'rlm_failure_stats'));
        $this->assertNotNull(SwooleCache::get('widget_stats', 'rlm_pattern_stats'));
    }

    public function test_warm_callback_makes_all_stats_available(): void
    {
        \App\Models\User::factory()->create();

        // Simulate what WarmSwooleCaches does
        $callbacks = SwooleCache::warmCallbacks();
        SwooleCache::warm('widget_stats', $callbacks['widget_stats'][0]);

        // All keys should be populated
        $this->assertNotNull(SwooleCache::get('widget_stats', 'rlm_failure_stats'));
        $this->assertNotNull(SwooleCache::get('widget_stats', 'rlm_pattern_stats'));
        $this->assertNotNull(SwooleCache::get('widget_stats', 'generation_trace_stats'));
        $this->assertNotNull(SwooleCache::get('widget_stats', 'project_health'));
        $this->assertNotNull(SwooleCache::get('widget_stats', 'failure_report_stats'));
        $this->assertNotNull(SwooleCache::get('widget_stats', 'rlm_lesson_stats'));
        $this->assertNotNull(SwooleCache::get('widget_stats', 'prevention_rule_stats'));
        $this->assertNotNull(SwooleCache::get('widget_stats', 'failure_by_status'));
        $this->assertNotNull(SwooleCache::get('widget_stats', 'failure_by_category'));
        $this->assertNotNull(SwooleCache::get('widget_stats', 'failure_trend'));

        $this->assertSame(10, SwooleCache::count('widget_stats'));
    }

    public function test_cache_count_tracks_entries(): void
    {
        \App\Models\User::factory()->create();

        $this->assertSame(0, SwooleCache::count('widget_stats'));

        WidgetStatsCacheManager::getOrCompute(
            'rlm_failure_stats',
            [WidgetStatsCacheManager::class, 'computeRlmFailureStats'],
        );

        $this->assertSame(1, SwooleCache::count('widget_stats'));

        WidgetStatsCacheManager::getOrCompute(
            'rlm_pattern_stats',
            [WidgetStatsCacheManager::class, 'computeRlmPatternStats'],
        );

        $this->assertSame(2, SwooleCache::count('widget_stats'));
    }

    public function test_generation_trace_affects_both_trace_and_health_caches(): void
    {
        \App\Models\User::factory()->create();

        // Populate both
        WidgetStatsCacheManager::getOrCompute(
            'generation_trace_stats',
            [WidgetStatsCacheManager::class, 'computeGenerationTraceStats'],
        );
        WidgetStatsCacheManager::getOrCompute(
            'project_health',
            [WidgetStatsCacheManager::class, 'computeProjectHealth'],
        );

        $this->assertNotNull(SwooleCache::get('widget_stats', 'generation_trace_stats'));
        $this->assertNotNull(SwooleCache::get('widget_stats', 'project_health'));

        // Creating a trace invalidates both
        GenerationTrace::factory()->create();

        $this->assertNull(SwooleCache::get('widget_stats', 'generation_trace_stats'));
        $this->assertNull(SwooleCache::get('widget_stats', 'project_health'));
    }

    public function test_non_octane_environment_falls_through_to_direct_queries(): void
    {
        SwooleCache::reset();

        // Re-register all tables but don't set resolver — not available
        SwooleCache::register('widget_stats', rows: 100, ttl: 60, valueSize: 2000);
        SwooleCache::register('notification_badges', rows: 1000, ttl: 60, valueSize: 100);
        SwooleCache::register('permissions', rows: 2000, ttl: 300, valueSize: 5000);
        SwooleCache::register('rlm_stats', rows: 10, ttl: 300, valueSize: 5000);
        SwooleCache::register('service_health', rows: 10, ttl: 30, valueSize: 200);

        $this->assertFalse(SwooleCache::isAvailable());

        \App\Models\User::factory()->create();
        RlmFailure::factory()->count(2)->create();

        $result = WidgetStatsCacheManager::getOrCompute(
            'rlm_failure_stats',
            [WidgetStatsCacheManager::class, 'computeRlmFailureStats'],
        );

        $this->assertSame(2, $result['total']);

        // Re-set the resolver for tearDown
        SwooleCache::useResolver(function (string $table): ?object {
            if (! isset($this->tables[$table])) {
                $this->tables[$table] = [];
            }

            return $this->createMockTable($table);
        });
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
