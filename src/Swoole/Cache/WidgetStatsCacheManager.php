<?php

namespace Aicl\Swoole\Cache;

use Aicl\Models\FailureReport;
use Aicl\Models\GenerationTrace;
use Aicl\Models\PreventionRule;
use Aicl\Models\RlmFailure;
use Aicl\Models\RlmLesson;
use Aicl\Models\RlmPattern;
use Aicl\Swoole\SwooleCache;
use Illuminate\Support\Facades\Event;

/**
 * Wires SwooleCache as L1 cache for dashboard widget statistics.
 *
 * Registers a table for widget aggregate data (counts, averages, sums),
 * warms it on worker start, and invalidates on relevant model events.
 * Each widget group gets its own cache key so invalidating one doesn't
 * flush others.
 */
class WidgetStatsCacheManager
{
    public const TABLE_NAME = 'widget_stats';

    public const TABLE_ROWS = 100;

    public const TABLE_TTL = 60;

    public const TABLE_VALUE_SIZE = 2000;

    /**
     * Register the widget stats cache table, warm callback, and invalidation listeners.
     */
    public static function register(): void
    {
        static::registerTable();
        static::registerWarmCallback();
        static::registerInvalidationListeners();
    }

    /**
     * Get a cached value or compute and store it.
     *
     * Widgets call this with their cache key and a closure that computes
     * the raw data. On cache hit, returns the cached array. On miss,
     * calls the closure, caches, and returns the result.
     *
     * @param  string  $key  Cache key (e.g., 'rlm_failure_stats')
     * @param  callable(): array  $compute  Callable that computes the data
     * @return array The cached or freshly computed data
     */
    public static function getOrCompute(string $key, callable $compute): array
    {
        if (! SwooleCache::isAvailable()) {
            return $compute();
        }

        $cached = SwooleCache::get(static::TABLE_NAME, $key);

        if ($cached !== null) {
            return $cached;
        }

        $data = $compute();
        SwooleCache::set(static::TABLE_NAME, $key, $data);

        return $data;
    }

    /**
     * Compute all widget stats for warming.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function computeAllStats(): array
    {
        return [
            'rlm_failure_stats' => static::computeRlmFailureStats(),
            'rlm_pattern_stats' => static::computeRlmPatternStats(),
            'generation_trace_stats' => static::computeGenerationTraceStats(),
            'project_health' => static::computeProjectHealth(),
            'failure_report_stats' => static::computeFailureReportStats(),
            'rlm_lesson_stats' => static::computeRlmLessonStats(),
            'prevention_rule_stats' => static::computePreventionRuleStats(),
            'failure_by_status' => static::computeFailureByStatus(),
            'failure_by_category' => static::computeFailureByCategory(),
            'failure_trend' => static::computeFailureTrend(),
        ];
    }

    /**
     * @return array{total: int, critical: int, high: int, promotable: int}
     */
    public static function computeRlmFailureStats(): array
    {
        return [
            'total' => RlmFailure::query()->count(),
            'critical' => RlmFailure::query()->where('severity', 'critical')->count(),
            'high' => RlmFailure::query()->where('severity', 'high')->count(),
            'promotable' => RlmFailure::query()->promotable()->count(),
        ];
    }

    /**
     * @return array{total: int, active: int, total_pass: int, total_eval: int}
     */
    public static function computeRlmPatternStats(): array
    {
        $total = RlmPattern::query()->count();
        $active = RlmPattern::query()->where('is_active', true)->count();

        $evaluated = RlmPattern::query()
            ->whereNotNull('last_evaluated_at')
            ->selectRaw('COALESCE(SUM(pass_count), 0) as total_pass, COALESCE(SUM(pass_count + fail_count), 0) as total_eval')
            ->first();

        return [
            'total' => $total,
            'active' => $active,
            'total_pass' => (int) $evaluated->getAttribute('total_pass'),
            'total_eval' => (int) $evaluated->getAttribute('total_eval'),
        ];
    }

    /**
     * @return array{total: int, avg_structural: float, avg_semantic: float, avg_fix_iterations: float}
     */
    public static function computeGenerationTraceStats(): array
    {
        return [
            'total' => GenerationTrace::query()->count(),
            'avg_structural' => (float) (GenerationTrace::query()->whereNotNull('structural_score')->avg('structural_score') ?? 0),
            'avg_semantic' => (float) (GenerationTrace::query()->whereNotNull('semantic_score')->avg('semantic_score') ?? 0),
            'avg_fix_iterations' => (float) (GenerationTrace::query()->avg('fix_iterations') ?? 0),
        ];
    }

    /**
     * @return array{total: int, avg_structural: float, avg_semantic: float, perfect_scores: int}
     */
    public static function computeProjectHealth(): array
    {
        return [
            'total' => GenerationTrace::query()->count(),
            'avg_structural' => (float) (GenerationTrace::query()->avg('structural_score') ?? 0),
            'avg_semantic' => (float) (GenerationTrace::query()->whereNotNull('semantic_score')->avg('semantic_score') ?? 0),
            'perfect_scores' => GenerationTrace::query()->where('structural_score', '>=', 100)->count(),
        ];
    }

    /**
     * @return array{total: int, resolved: int, unresolved: int, avg_time_to_resolve: float|null}
     */
    public static function computeFailureReportStats(): array
    {
        $total = FailureReport::query()->count();
        $resolved = FailureReport::query()->resolved()->count();

        return [
            'total' => $total,
            'resolved' => $resolved,
            'unresolved' => FailureReport::query()->unresolved()->count(),
            'avg_time_to_resolve' => FailureReport::query()->resolved()
                ->whereNotNull('time_to_resolve')
                ->avg('time_to_resolve'),
        ];
    }

    /**
     * @return array{total: int, verified: int, unverified: int, avg_confidence: float|null}
     */
    public static function computeRlmLessonStats(): array
    {
        return [
            'total' => RlmLesson::query()->count(),
            'verified' => RlmLesson::query()->verified()->count(),
            'unverified' => RlmLesson::query()->unverified()->count(),
            'avg_confidence' => RlmLesson::query()->avg('confidence'),
        ];
    }

    /**
     * @return array{total: int, active: int, avg_confidence: float, total_applied: int}
     */
    public static function computePreventionRuleStats(): array
    {
        return [
            'total' => PreventionRule::query()->count(),
            'active' => PreventionRule::query()->where('is_active', true)->count(),
            'avg_confidence' => (float) (PreventionRule::query()->where('is_active', true)->avg('confidence') ?? 0),
            'total_applied' => (int) PreventionRule::query()->sum('applied_count'),
        ];
    }

    /**
     * @return array<string, int>
     */
    public static function computeFailureByStatus(): array
    {
        return RlmFailure::query()
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();
    }

    /**
     * @return array<string, int>
     */
    public static function computeFailureByCategory(): array
    {
        return RlmFailure::query()
            ->selectRaw('category, COUNT(*) as count')
            ->groupBy('category')
            ->pluck('count', 'category')
            ->toArray();
    }

    /**
     * @return array{labels: list<string>, counts: list<int>}
     */
    public static function computeFailureTrend(): array
    {
        $months = collect(range(5, 0))->map(fn (int $i) => now()->subMonths($i)->startOfMonth());

        return [
            'labels' => $months->map(fn ($month) => $month->format('M Y'))->values()->toArray(),
            'counts' => $months->map(fn ($month) => FailureReport::query()
                ->whereBetween('created_at', [$month, $month->copy()->endOfMonth()])
                ->count()
            )->values()->toArray(),
        ];
    }

    protected static function registerTable(): void
    {
        SwooleCache::register(
            static::TABLE_NAME,
            rows: static::TABLE_ROWS,
            ttl: static::TABLE_TTL,
            valueSize: static::TABLE_VALUE_SIZE,
        );
    }

    protected static function registerWarmCallback(): void
    {
        SwooleCache::registerWarm(static::TABLE_NAME, function (): array {
            return static::computeAllStats();
        });
    }

    /**
     * Register event-driven invalidation listeners.
     *
     * Model created/deleted events invalidate the relevant widget cache keys.
     * Uses Eloquent string events for broad coverage.
     */
    protected static function registerInvalidationListeners(): void
    {
        // RlmFailure changes → failure stats, charts
        $failureKeys = ['rlm_failure_stats', 'failure_by_status', 'failure_by_category'];
        static::invalidateKeysOnModelEvents(RlmFailure::class, $failureKeys);

        // RlmPattern changes → pattern stats
        static::invalidateKeysOnModelEvents(RlmPattern::class, ['rlm_pattern_stats']);

        // GenerationTrace changes → trace stats + project health
        static::invalidateKeysOnModelEvents(GenerationTrace::class, ['generation_trace_stats', 'project_health']);

        // FailureReport changes → report stats + trend
        static::invalidateKeysOnModelEvents(FailureReport::class, ['failure_report_stats', 'failure_trend']);

        // RlmLesson changes → lesson stats
        static::invalidateKeysOnModelEvents(RlmLesson::class, ['rlm_lesson_stats']);

        // PreventionRule changes → rule stats
        static::invalidateKeysOnModelEvents(PreventionRule::class, ['prevention_rule_stats']);
    }

    /**
     * Register created/deleted/updated Eloquent event listeners that invalidate specific cache keys.
     *
     * @param  class-string  $model
     * @param  list<string>  $keys
     */
    protected static function invalidateKeysOnModelEvents(string $model, array $keys): void
    {
        $invalidate = function () use ($keys): void {
            foreach ($keys as $key) {
                SwooleCache::forget(static::TABLE_NAME, $key);
            }
        };

        Event::listen("eloquent.created: {$model}", $invalidate);
        Event::listen("eloquent.deleted: {$model}", $invalidate);
        Event::listen("eloquent.updated: {$model}", $invalidate);
    }
}
