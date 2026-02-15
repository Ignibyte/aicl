<?php

namespace Aicl\Tests\Unit\Swoole;

use Aicl\Models\FailureReport;
use Aicl\Models\GenerationTrace;
use Aicl\Models\PreventionRule;
use Aicl\Models\RlmFailure;
use Aicl\Models\RlmLesson;
use Aicl\Models\RlmPattern;
use Aicl\Swoole\Cache\WidgetStatsCacheManager;
use Aicl\Swoole\SwooleCache;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WidgetStatsCacheManagerTest extends TestCase
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

    // -- Constants --

    public function test_table_constants_are_correct(): void
    {
        $this->assertSame('widget_stats', WidgetStatsCacheManager::TABLE_NAME);
        $this->assertSame(100, WidgetStatsCacheManager::TABLE_ROWS);
        $this->assertSame(60, WidgetStatsCacheManager::TABLE_TTL);
        $this->assertSame(2000, WidgetStatsCacheManager::TABLE_VALUE_SIZE);
    }

    public function test_table_is_registered_with_correct_params(): void
    {
        $registrations = SwooleCache::registrations();

        $this->assertArrayHasKey('widget_stats', $registrations);
        $this->assertSame(100, $registrations['widget_stats']['rows']);
        $this->assertSame(60, $registrations['widget_stats']['ttl']);
        $this->assertSame(2000, $registrations['widget_stats']['valueSize']);
    }

    // -- getOrCompute --

    public function test_get_or_compute_returns_computed_data_on_cache_miss(): void
    {
        $result = WidgetStatsCacheManager::getOrCompute('test_key', fn () => ['count' => 42]);

        $this->assertSame(['count' => 42], $result);

        // Should now be cached
        $cached = SwooleCache::get('widget_stats', 'test_key');
        $this->assertSame(['count' => 42], $cached);
    }

    public function test_get_or_compute_returns_cached_data_on_cache_hit(): void
    {
        // Pre-populate cache
        SwooleCache::set('widget_stats', 'test_key', ['count' => 42]);

        $callCount = 0;
        $result = WidgetStatsCacheManager::getOrCompute('test_key', function () use (&$callCount) {
            $callCount++;

            return ['count' => 99];
        });

        $this->assertSame(['count' => 42], $result);
        $this->assertSame(0, $callCount); // compute closure was NOT called
    }

    public function test_get_or_compute_recomputes_after_ttl_expiry(): void
    {
        // First call populates cache
        WidgetStatsCacheManager::getOrCompute('test_key', fn () => ['count' => 42]);
        $this->assertNotNull(SwooleCache::get('widget_stats', 'test_key'));

        // Advance time past TTL (60 seconds)
        Carbon::setTestNow(Carbon::now()->addSeconds(61));

        // Cache expired — should recompute
        $result = WidgetStatsCacheManager::getOrCompute('test_key', fn () => ['count' => 99]);
        $this->assertSame(['count' => 99], $result);
    }

    public function test_get_or_compute_falls_through_when_cache_unavailable(): void
    {
        SwooleCache::reset();
        // Don't set resolver — SwooleCache::isAvailable() returns false

        // Re-register the table (required for resolveTable to not throw)
        SwooleCache::register('widget_stats', rows: 100, ttl: 60, valueSize: 2000);

        $result = WidgetStatsCacheManager::getOrCompute('test_key', fn () => ['count' => 42]);

        $this->assertSame(['count' => 42], $result);
    }

    // -- Compute methods --

    public function test_compute_rlm_failure_stats_returns_correct_structure(): void
    {
        $owner = $this->createOwner();

        RlmFailure::factory()->for($owner, 'owner')->create(['severity' => 'critical', 'report_count' => 1]);
        RlmFailure::factory()->for($owner, 'owner')->create(['severity' => 'high', 'report_count' => 1]);
        RlmFailure::factory()->for($owner, 'owner')->create(['severity' => 'medium', 'report_count' => 1]);
        RlmFailure::factory()->for($owner, 'owner')->count(2)->create(['severity' => 'critical', 'report_count' => 5]);

        $stats = WidgetStatsCacheManager::computeRlmFailureStats();

        $this->assertSame(5, $stats['total']);
        $this->assertSame(3, $stats['critical']);
        $this->assertSame(1, $stats['high']);
        $this->assertArrayHasKey('promotable', $stats);
    }

    public function test_compute_rlm_pattern_stats_returns_correct_structure(): void
    {
        $owner = $this->createOwner();

        RlmPattern::factory()->for($owner, 'owner')->create(['is_active' => true, 'pass_count' => 10, 'fail_count' => 2, 'last_evaluated_at' => now()]);
        RlmPattern::factory()->for($owner, 'owner')->create(['is_active' => true, 'pass_count' => 8, 'fail_count' => 0, 'last_evaluated_at' => now()]);
        RlmPattern::factory()->for($owner, 'owner')->create(['is_active' => false, 'pass_count' => 0, 'fail_count' => 0, 'last_evaluated_at' => null]);

        $stats = WidgetStatsCacheManager::computeRlmPatternStats();

        $this->assertSame(3, $stats['total']);
        $this->assertSame(2, $stats['active']);
        $this->assertSame(18, $stats['total_pass']);
        $this->assertSame(20, $stats['total_eval']);
    }

    public function test_compute_generation_trace_stats_returns_correct_structure(): void
    {
        $owner = $this->createOwner();

        GenerationTrace::factory()->for($owner, 'owner')->create(['structural_score' => 90, 'semantic_score' => 85, 'fix_iterations' => 2]);
        GenerationTrace::factory()->for($owner, 'owner')->create(['structural_score' => 100, 'semantic_score' => null, 'fix_iterations' => 0]);

        $stats = WidgetStatsCacheManager::computeGenerationTraceStats();

        $this->assertSame(2, $stats['total']);
        $this->assertEqualsWithDelta(95.0, $stats['avg_structural'], 0.1);
        $this->assertEqualsWithDelta(85.0, $stats['avg_semantic'], 0.1);
        $this->assertEqualsWithDelta(1.0, $stats['avg_fix_iterations'], 0.1);
    }

    public function test_compute_project_health_returns_correct_structure(): void
    {
        $owner = $this->createOwner();

        GenerationTrace::factory()->for($owner, 'owner')->create(['structural_score' => 100, 'semantic_score' => 95]);
        GenerationTrace::factory()->for($owner, 'owner')->create(['structural_score' => 80, 'semantic_score' => null]);

        $stats = WidgetStatsCacheManager::computeProjectHealth();

        $this->assertSame(2, $stats['total']);
        $this->assertArrayHasKey('avg_structural', $stats);
        $this->assertArrayHasKey('avg_semantic', $stats);
        $this->assertSame(1, $stats['perfect_scores']);
    }

    public function test_compute_failure_report_stats_returns_correct_structure(): void
    {
        $owner = $this->createOwner();
        $failure = RlmFailure::factory()->for($owner, 'owner')->create();

        FailureReport::factory()->for($owner, 'owner')->for($failure, 'failure')->resolved()->create(['time_to_resolve' => 30]);
        FailureReport::factory()->for($owner, 'owner')->for($failure, 'failure')->unresolved()->create();

        $stats = WidgetStatsCacheManager::computeFailureReportStats();

        $this->assertSame(2, $stats['total']);
        $this->assertSame(1, $stats['resolved']);
        $this->assertSame(1, $stats['unresolved']);
        $this->assertEqualsWithDelta(30.0, $stats['avg_time_to_resolve'], 0.1);
    }

    public function test_compute_rlm_lesson_stats_returns_correct_structure(): void
    {
        $owner = $this->createOwner();

        RlmLesson::factory()->for($owner, 'owner')->create(['is_verified' => true, 'confidence' => 0.9]);
        RlmLesson::factory()->for($owner, 'owner')->create(['is_verified' => false, 'confidence' => 0.6]);

        $stats = WidgetStatsCacheManager::computeRlmLessonStats();

        $this->assertSame(2, $stats['total']);
        $this->assertSame(1, $stats['verified']);
        $this->assertSame(1, $stats['unverified']);
        $this->assertEqualsWithDelta(0.75, $stats['avg_confidence'], 0.01);
    }

    public function test_compute_prevention_rule_stats_returns_correct_structure(): void
    {
        $owner = $this->createOwner();

        PreventionRule::factory()->for($owner, 'owner')->create(['is_active' => true, 'confidence' => 0.9, 'applied_count' => 5]);
        PreventionRule::factory()->for($owner, 'owner')->create(['is_active' => true, 'confidence' => 0.8, 'applied_count' => 3]);
        PreventionRule::factory()->for($owner, 'owner')->create(['is_active' => false, 'applied_count' => 1]);

        $stats = WidgetStatsCacheManager::computePreventionRuleStats();

        $this->assertSame(3, $stats['total']);
        $this->assertSame(2, $stats['active']);
        $this->assertEqualsWithDelta(0.85, $stats['avg_confidence'], 0.01);
        $this->assertSame(9, $stats['total_applied']);
    }

    public function test_compute_failure_by_status_returns_grouped_counts(): void
    {
        $owner = $this->createOwner();

        RlmFailure::factory()->for($owner, 'owner')->reported()->create();
        RlmFailure::factory()->for($owner, 'owner')->reported()->create();
        RlmFailure::factory()->for($owner, 'owner')->resolved()->create();

        $stats = WidgetStatsCacheManager::computeFailureByStatus();

        $this->assertCount(2, $stats);
        $this->assertSame(3, array_sum($stats));
    }

    public function test_compute_failure_by_category_returns_grouped_counts(): void
    {
        $owner = $this->createOwner();

        RlmFailure::factory()->for($owner, 'owner')->create(['category' => 'scaffolding']);
        RlmFailure::factory()->for($owner, 'owner')->create(['category' => 'scaffolding']);
        RlmFailure::factory()->for($owner, 'owner')->create(['category' => 'testing']);

        $stats = WidgetStatsCacheManager::computeFailureByCategory();

        $this->assertSame(2, $stats['scaffolding']);
        $this->assertSame(1, $stats['testing']);
    }

    public function test_compute_failure_trend_returns_labels_and_counts(): void
    {
        $stats = WidgetStatsCacheManager::computeFailureTrend();

        $this->assertArrayHasKey('labels', $stats);
        $this->assertArrayHasKey('counts', $stats);
        $this->assertCount(6, $stats['labels']);
        $this->assertCount(6, $stats['counts']);
    }

    public function test_compute_all_stats_returns_all_keys(): void
    {
        $stats = WidgetStatsCacheManager::computeAllStats();

        $expectedKeys = [
            'rlm_failure_stats',
            'rlm_pattern_stats',
            'generation_trace_stats',
            'project_health',
            'failure_report_stats',
            'rlm_lesson_stats',
            'prevention_rule_stats',
            'failure_by_status',
            'failure_by_category',
            'failure_trend',
        ];

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $stats, "Missing key: {$key}");
        }
    }

    // -- Invalidation --

    public function test_rlm_failure_created_invalidates_failure_cache_keys(): void
    {
        $owner = $this->createOwner();

        // Pre-populate cache for failure-related keys
        SwooleCache::set('widget_stats', 'rlm_failure_stats', ['total' => 0]);
        SwooleCache::set('widget_stats', 'failure_by_status', []);
        SwooleCache::set('widget_stats', 'failure_by_category', []);
        SwooleCache::set('widget_stats', 'rlm_pattern_stats', ['total' => 5]); // unrelated

        RlmFailure::factory()->for($owner, 'owner')->create();

        // Failure-related keys should be invalidated
        $this->assertNull(SwooleCache::get('widget_stats', 'rlm_failure_stats'));
        $this->assertNull(SwooleCache::get('widget_stats', 'failure_by_status'));
        $this->assertNull(SwooleCache::get('widget_stats', 'failure_by_category'));

        // Unrelated key should remain
        $this->assertNotNull(SwooleCache::get('widget_stats', 'rlm_pattern_stats'));
    }

    public function test_rlm_pattern_created_invalidates_pattern_cache_key(): void
    {
        $owner = $this->createOwner();

        SwooleCache::set('widget_stats', 'rlm_pattern_stats', ['total' => 5]);
        SwooleCache::set('widget_stats', 'rlm_failure_stats', ['total' => 0]); // unrelated

        RlmPattern::factory()->for($owner, 'owner')->create();

        $this->assertNull(SwooleCache::get('widget_stats', 'rlm_pattern_stats'));
        $this->assertNotNull(SwooleCache::get('widget_stats', 'rlm_failure_stats'));
    }

    public function test_generation_trace_created_invalidates_trace_and_health_keys(): void
    {
        $owner = $this->createOwner();

        SwooleCache::set('widget_stats', 'generation_trace_stats', ['total' => 1]);
        SwooleCache::set('widget_stats', 'project_health', ['total' => 1]);

        GenerationTrace::factory()->for($owner, 'owner')->create();

        $this->assertNull(SwooleCache::get('widget_stats', 'generation_trace_stats'));
        $this->assertNull(SwooleCache::get('widget_stats', 'project_health'));
    }

    public function test_failure_report_created_invalidates_report_and_trend_keys(): void
    {
        $owner = $this->createOwner();

        SwooleCache::set('widget_stats', 'failure_report_stats', ['total' => 1]);
        SwooleCache::set('widget_stats', 'failure_trend', ['labels' => [], 'counts' => []]);

        FailureReport::factory()->for($owner, 'owner')->create();

        $this->assertNull(SwooleCache::get('widget_stats', 'failure_report_stats'));
        $this->assertNull(SwooleCache::get('widget_stats', 'failure_trend'));
    }

    public function test_rlm_lesson_created_invalidates_lesson_cache_key(): void
    {
        $owner = $this->createOwner();

        SwooleCache::set('widget_stats', 'rlm_lesson_stats', ['total' => 1]);

        RlmLesson::factory()->for($owner, 'owner')->create();

        $this->assertNull(SwooleCache::get('widget_stats', 'rlm_lesson_stats'));
    }

    public function test_prevention_rule_created_invalidates_rule_cache_key(): void
    {
        $owner = $this->createOwner();

        SwooleCache::set('widget_stats', 'prevention_rule_stats', ['total' => 1]);

        PreventionRule::factory()->for($owner, 'owner')->create();

        $this->assertNull(SwooleCache::get('widget_stats', 'prevention_rule_stats'));
    }

    public function test_model_deletion_invalidates_cache_key(): void
    {
        $owner = $this->createOwner();

        $failure = RlmFailure::factory()->for($owner, 'owner')->create();

        SwooleCache::set('widget_stats', 'rlm_failure_stats', ['total' => 1]);

        $failure->delete();

        $this->assertNull(SwooleCache::get('widget_stats', 'rlm_failure_stats'));
    }

    public function test_model_update_invalidates_cache_key(): void
    {
        $owner = $this->createOwner();

        $failure = RlmFailure::factory()->for($owner, 'owner')->create(['severity' => 'low']);

        SwooleCache::set('widget_stats', 'rlm_failure_stats', ['total' => 1]);

        $failure->update(['severity' => 'critical']);

        $this->assertNull(SwooleCache::get('widget_stats', 'rlm_failure_stats'));
    }

    // -- Warm callback --

    public function test_warm_callback_is_registered(): void
    {
        $warmCallbacks = SwooleCache::warmCallbacks();

        $this->assertArrayHasKey('widget_stats', $warmCallbacks);
        $this->assertCount(1, $warmCallbacks['widget_stats']);
    }

    public function test_warm_callback_populates_all_keys(): void
    {
        $this->createOwner();

        // Execute the warm callback
        $callbacks = SwooleCache::warmCallbacks();
        $data = ($callbacks['widget_stats'][0])();

        $this->assertArrayHasKey('rlm_failure_stats', $data);
        $this->assertArrayHasKey('rlm_pattern_stats', $data);
        $this->assertArrayHasKey('generation_trace_stats', $data);
        $this->assertArrayHasKey('project_health', $data);
        $this->assertArrayHasKey('failure_report_stats', $data);
        $this->assertArrayHasKey('rlm_lesson_stats', $data);
        $this->assertArrayHasKey('prevention_rule_stats', $data);
        $this->assertArrayHasKey('failure_by_status', $data);
        $this->assertArrayHasKey('failure_by_category', $data);
        $this->assertArrayHasKey('failure_trend', $data);
    }

    // -- Helpers --

    private function createOwner(): \App\Models\User
    {
        return \App\Models\User::factory()->create();
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
