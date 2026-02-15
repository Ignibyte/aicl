<?php

namespace Aicl\Swoole\Cache;

use Aicl\Models\GenerationTrace;
use Aicl\Models\GoldenAnnotation;
use Aicl\Models\PreventionRule;
use Aicl\Models\RlmFailure;
use Aicl\Models\RlmLesson;
use Aicl\Models\RlmPattern;
use Aicl\Models\RlmScore;
use Aicl\Swoole\SwooleCache;
use Illuminate\Support\Facades\Event;

/**
 * Wires SwooleCache as L1 cache for KnowledgeService::stats().
 *
 * The stats() method runs 10+ COUNT queries across 6 tables on every call.
 * This manager caches the aggregation results with a 5-minute TTL and
 * invalidates on any RLM model creation/deletion.
 */
class KnowledgeStatsCacheManager
{
    public const TABLE_NAME = 'rlm_stats';

    public const TABLE_ROWS = 10;

    public const TABLE_TTL = 300;

    public const TABLE_VALUE_SIZE = 5000;

    public const CACHE_KEY = 'global';

    /**
     * Register the knowledge stats cache table, warm callback, and invalidation listeners.
     */
    public static function register(): void
    {
        static::registerTable();
        static::registerWarmCallback();
        static::registerInvalidationListeners();
    }

    /**
     * Get cached stats or return null (cache miss).
     *
     * KnowledgeService::stats() calls this first. On miss, the service
     * computes stats and calls storeStats() to cache them.
     *
     * @return array<string, mixed>|null
     */
    public static function getCachedStats(): ?array
    {
        if (! SwooleCache::isAvailable()) {
            return null;
        }

        return SwooleCache::get(static::TABLE_NAME, static::CACHE_KEY);
    }

    /**
     * Store computed stats in the cache.
     *
     * @param  array<string, mixed>  $stats
     */
    public static function storeStats(array $stats): void
    {
        if (! SwooleCache::isAvailable()) {
            return;
        }

        SwooleCache::set(static::TABLE_NAME, static::CACHE_KEY, $stats);
    }

    /**
     * Invalidate the cached stats.
     */
    public static function invalidate(): void
    {
        SwooleCache::forget(static::TABLE_NAME, static::CACHE_KEY);
    }

    /**
     * Compute the database aggregation stats (without service status checks).
     *
     * Used for warming. Service status (ES, embeddings) is excluded
     * because those are runtime checks that shouldn't be cached long-term.
     *
     * @return array<string, mixed>
     */
    public static function computeStats(): array
    {
        $patternCount = RlmPattern::query()->where('is_active', true)->count();
        $failureTotal = RlmFailure::query()->count();
        $failureActive = RlmFailure::query()->where('is_active', true)->count();
        $lessonCount = RlmLesson::query()->count();
        $lessonVerified = RlmLesson::query()->where('is_verified', true)->count();
        $scoreEntities = RlmScore::query()->distinct('entity_name')->count('entity_name');
        $traceCount = GenerationTrace::query()->count();
        $preventionRuleCount = PreventionRule::query()->where('is_active', true)->count();
        $goldenAnnotationCount = GoldenAnnotation::query()->where('is_active', true)->count();

        $lessonsByTopic = RlmLesson::query()
            ->selectRaw('topic, COUNT(*) as count')
            ->groupBy('topic')
            ->orderByDesc('count')
            ->pluck('count', 'topic')
            ->all();

        $topFailingPatterns = RlmFailure::query()
            ->where('is_active', true)
            ->orderByDesc('report_count')
            ->limit(5)
            ->get(['failure_code', 'title', 'report_count', 'severity'])
            ->toArray();

        return [
            'patterns' => $patternCount,
            'failures' => [
                'total' => $failureTotal,
                'active' => $failureActive,
            ],
            'lessons' => [
                'total' => $lessonCount,
                'verified' => $lessonVerified,
                'unverified' => $lessonCount - $lessonVerified,
                'by_topic' => $lessonsByTopic,
            ],
            'scores' => $scoreEntities,
            'traces' => $traceCount,
            'prevention_rules' => $preventionRuleCount,
            'golden_annotations' => $goldenAnnotationCount,
            'top_failing' => $topFailingPatterns,
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
            return [static::CACHE_KEY => static::computeStats()];
        });
    }

    /**
     * Register event-driven invalidation listeners.
     *
     * Any RLM model creation/deletion/update invalidates the stats cache.
     */
    protected static function registerInvalidationListeners(): void
    {
        $models = [
            RlmPattern::class,
            RlmFailure::class,
            RlmLesson::class,
            GenerationTrace::class,
            PreventionRule::class,
            GoldenAnnotation::class,
            RlmScore::class,
        ];

        $invalidate = fn () => static::invalidate();

        foreach ($models as $model) {
            Event::listen("eloquent.created: {$model}", $invalidate);
            Event::listen("eloquent.deleted: {$model}", $invalidate);
            Event::listen("eloquent.updated: {$model}", $invalidate);
        }
    }
}
