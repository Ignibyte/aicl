<?php

declare(strict_types=1);

namespace Aicl\Swoole\Cache;

use Aicl\Swoole\SwooleCache;

/**
 * Wires SwooleCache as L1 cache for service health checks.
 *
 * Caches the results of expensive health check HTTP calls (e.g.,
 * Elasticsearch availability) so repeated search operations within
 * the TTL window don't fire redundant HTTP probes.
 *
 * TTL-only invalidation — no event-driven invalidation needed
 * since health status is determined by external services.
 */
class ServiceHealthCacheManager
{
    public const TABLE_NAME = 'service_health';

    public const TABLE_ROWS = 10;

    public const TABLE_TTL = 30;

    public const TABLE_VALUE_SIZE = 200;

    /**
     * Register the service health cache table.
     *
     * No warm callback (lazy population on first access).
     * No event-driven invalidation (TTL-only).
     */
    public static function register(): void
    {
        SwooleCache::register(
            static::TABLE_NAME,
            rows: static::TABLE_ROWS,
            ttl: static::TABLE_TTL,
            valueSize: static::TABLE_VALUE_SIZE,
        );
    }

    /**
     * Get the cached health status for a service.
     *
     * Returns the cached boolean availability, or null on cache miss.
     */
    public static function getCachedAvailability(string $service): ?bool
    {
        // @codeCoverageIgnoreStart — Swoole runtime
        if (! SwooleCache::isAvailable()) {
            return null;
        }

        $cached = SwooleCache::get(static::TABLE_NAME, $service);

        if ($cached === null) {
            return null;
        }

        return $cached['available'] ?? null;
        // @codeCoverageIgnoreEnd
    }

    /**
     * Store the health status for a service.
     */
    public static function storeAvailability(string $service, bool $available): void
    {
        // @codeCoverageIgnoreStart — Swoole runtime
        if (! SwooleCache::isAvailable()) {
            return;
        }

        SwooleCache::set(static::TABLE_NAME, $service, ['available' => $available]);
        // @codeCoverageIgnoreEnd
    }

    /**
     * Invalidate the cached health status for a service.
     */
    public static function invalidate(string $service): void
    {
        // @codeCoverageIgnoreStart — Swoole runtime
        SwooleCache::forget(static::TABLE_NAME, $service);
        // @codeCoverageIgnoreEnd
    }
}
