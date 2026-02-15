<?php

namespace Aicl\Tests\Unit\Swoole;

use Aicl\Models\GenerationTrace;
use Aicl\Models\GoldenAnnotation;
use Aicl\Models\PreventionRule;
use Aicl\Models\RlmFailure;
use Aicl\Models\RlmLesson;
use Aicl\Models\RlmPattern;
use Aicl\Models\RlmScore;
use Aicl\Swoole\Cache\KnowledgeStatsCacheManager;
use Aicl\Swoole\SwooleCache;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class KnowledgeStatsCacheManagerTest extends TestCase
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
        SwooleCache::register('permissions', rows: 2000, ttl: 300, valueSize: 5000);
        SwooleCache::register('service_health', rows: 10, ttl: 30, valueSize: 200);

        KnowledgeStatsCacheManager::register();
    }

    protected function tearDown(): void
    {
        SwooleCache::reset();
        $this->tables = [];
        Carbon::setTestNow();

        parent::tearDown();
    }

    // -- Constants --

    public function test_table_name_is_rlm_stats(): void
    {
        $this->assertSame('rlm_stats', KnowledgeStatsCacheManager::TABLE_NAME);
    }

    public function test_table_rows_is_10(): void
    {
        $this->assertSame(10, KnowledgeStatsCacheManager::TABLE_ROWS);
    }

    public function test_table_ttl_is_300(): void
    {
        $this->assertSame(300, KnowledgeStatsCacheManager::TABLE_TTL);
    }

    public function test_table_value_size_is_5000(): void
    {
        $this->assertSame(5000, KnowledgeStatsCacheManager::TABLE_VALUE_SIZE);
    }

    public function test_cache_key_is_global(): void
    {
        $this->assertSame('global', KnowledgeStatsCacheManager::CACHE_KEY);
    }

    // -- Registration --

    public function test_register_creates_swoole_cache_table(): void
    {
        $registrations = SwooleCache::registrations();

        $this->assertArrayHasKey('rlm_stats', $registrations);
        $this->assertSame(10, $registrations['rlm_stats']['rows']);
        $this->assertSame(300, $registrations['rlm_stats']['ttl']);
        $this->assertSame(5000, $registrations['rlm_stats']['valueSize']);
    }

    public function test_register_adds_warm_callback(): void
    {
        $callbacks = SwooleCache::warmCallbacks();

        $this->assertArrayHasKey('rlm_stats', $callbacks);
        $this->assertCount(1, $callbacks['rlm_stats']);
    }

    // -- getCachedStats / storeStats --

    public function test_get_cached_stats_returns_null_on_miss(): void
    {
        $this->assertNull(KnowledgeStatsCacheManager::getCachedStats());
    }

    public function test_store_stats_and_get_cached_stats(): void
    {
        $stats = ['patterns' => 42, 'traces' => 10];

        KnowledgeStatsCacheManager::storeStats($stats);

        $cached = KnowledgeStatsCacheManager::getCachedStats();
        $this->assertSame(42, $cached['patterns']);
        $this->assertSame(10, $cached['traces']);
    }

    public function test_get_cached_stats_returns_null_when_swoole_unavailable(): void
    {
        SwooleCache::reset();
        SwooleCache::register('rlm_stats', rows: 10, ttl: 300, valueSize: 5000);

        $this->assertFalse(SwooleCache::isAvailable());
        $this->assertNull(KnowledgeStatsCacheManager::getCachedStats());

        // Re-set resolver for tearDown
        SwooleCache::useResolver(function (string $table): ?object {
            if (! isset($this->tables[$table])) {
                $this->tables[$table] = [];
            }

            return $this->createMockTable($table);
        });
    }

    // -- invalidate --

    public function test_invalidate_clears_cached_stats(): void
    {
        KnowledgeStatsCacheManager::storeStats(['patterns' => 10]);
        $this->assertNotNull(KnowledgeStatsCacheManager::getCachedStats());

        KnowledgeStatsCacheManager::invalidate();

        $this->assertNull(KnowledgeStatsCacheManager::getCachedStats());
    }

    // -- TTL --

    public function test_cached_stats_expire_after_ttl(): void
    {
        KnowledgeStatsCacheManager::storeStats(['patterns' => 10]);
        $this->assertNotNull(KnowledgeStatsCacheManager::getCachedStats());

        // Advance past 300s TTL
        Carbon::setTestNow(now()->addSeconds(301));

        $this->assertNull(KnowledgeStatsCacheManager::getCachedStats());
    }

    // -- computeStats --

    public function test_compute_stats_returns_correct_aggregations(): void
    {
        Http::fake();

        $owner = \App\Models\User::factory()->create();

        RlmPattern::factory()->count(3)->create(['is_active' => true]);
        RlmPattern::factory()->create(['is_active' => false]);

        RlmFailure::factory()->count(2)->for($owner, 'owner')->create();

        RlmLesson::factory()->count(2)->for($owner, 'owner')->create(['is_verified' => true, 'topic' => 'testing']);
        RlmLesson::factory()->for($owner, 'owner')->create(['is_verified' => false, 'topic' => 'filament']);

        GenerationTrace::factory()->count(2)->for($owner, 'owner')->create();

        PreventionRule::factory()->withoutFailure()->count(2)->for($owner, 'owner')->create(['is_active' => true]);
        PreventionRule::factory()->withoutFailure()->for($owner, 'owner')->create(['is_active' => false]);

        GoldenAnnotation::factory()->count(4)->create(['is_active' => true]);
        GoldenAnnotation::factory()->create(['is_active' => false]);

        $stats = KnowledgeStatsCacheManager::computeStats();

        $this->assertSame(3, $stats['patterns']);
        $this->assertSame(2, $stats['failures']['total']);
        $this->assertSame(3, $stats['lessons']['total']);
        $this->assertSame(2, $stats['lessons']['verified']);
        $this->assertSame(1, $stats['lessons']['unverified']);
        $this->assertSame(2, $stats['traces']);
        $this->assertSame(2, $stats['prevention_rules']);
        $this->assertSame(4, $stats['golden_annotations']);
        $this->assertArrayHasKey('by_topic', $stats['lessons']);
        $this->assertArrayHasKey('top_failing', $stats);
    }

    // -- Event-driven Invalidation --

    public function test_pattern_created_invalidates_cache(): void
    {
        KnowledgeStatsCacheManager::storeStats(['patterns' => 10]);
        $this->assertNotNull(KnowledgeStatsCacheManager::getCachedStats());

        RlmPattern::factory()->create();

        $this->assertNull(KnowledgeStatsCacheManager::getCachedStats());
    }

    public function test_failure_created_invalidates_cache(): void
    {
        $owner = \App\Models\User::factory()->create();

        KnowledgeStatsCacheManager::storeStats(['patterns' => 10]);

        RlmFailure::factory()->for($owner, 'owner')->create();

        $this->assertNull(KnowledgeStatsCacheManager::getCachedStats());
    }

    public function test_lesson_created_invalidates_cache(): void
    {
        $owner = \App\Models\User::factory()->create();

        KnowledgeStatsCacheManager::storeStats(['patterns' => 10]);

        RlmLesson::factory()->for($owner, 'owner')->create();

        $this->assertNull(KnowledgeStatsCacheManager::getCachedStats());
    }

    public function test_trace_created_invalidates_cache(): void
    {
        $owner = \App\Models\User::factory()->create();

        KnowledgeStatsCacheManager::storeStats(['patterns' => 10]);

        GenerationTrace::factory()->for($owner, 'owner')->create();

        $this->assertNull(KnowledgeStatsCacheManager::getCachedStats());
    }

    public function test_prevention_rule_created_invalidates_cache(): void
    {
        $owner = \App\Models\User::factory()->create();

        KnowledgeStatsCacheManager::storeStats(['patterns' => 10]);

        PreventionRule::factory()->for($owner, 'owner')->create();

        $this->assertNull(KnowledgeStatsCacheManager::getCachedStats());
    }

    public function test_golden_annotation_created_invalidates_cache(): void
    {
        KnowledgeStatsCacheManager::storeStats(['patterns' => 10]);

        GoldenAnnotation::factory()->create();

        $this->assertNull(KnowledgeStatsCacheManager::getCachedStats());
    }

    public function test_score_created_invalidates_cache(): void
    {
        $owner = \App\Models\User::factory()->create();

        KnowledgeStatsCacheManager::storeStats(['patterns' => 10]);

        RlmScore::factory()->for($owner, 'owner')->create();

        $this->assertNull(KnowledgeStatsCacheManager::getCachedStats());
    }

    // -- Warm callback --

    public function test_warm_callback_populates_global_stats(): void
    {
        \App\Models\User::factory()->create();

        $callbacks = SwooleCache::warmCallbacks();
        SwooleCache::warm('rlm_stats', $callbacks['rlm_stats'][0]);

        $cached = KnowledgeStatsCacheManager::getCachedStats();
        $this->assertNotNull($cached);
        $this->assertArrayHasKey('patterns', $cached);
        $this->assertArrayHasKey('failures', $cached);
        $this->assertArrayHasKey('lessons', $cached);
        $this->assertArrayHasKey('traces', $cached);
        $this->assertArrayHasKey('prevention_rules', $cached);
        $this->assertArrayHasKey('golden_annotations', $cached);
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
