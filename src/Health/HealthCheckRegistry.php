<?php

declare(strict_types=1);

namespace Aicl\Health;

use Aicl\Health\Contracts\ServiceHealthCheck;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Cache;

/**
 * HealthCheckRegistry.
 */
class HealthCheckRegistry
{
    private const CACHE_KEY = 'aicl:health_checks';

    private const CACHE_TTL = 30;

    /** @var array<class-string<ServiceHealthCheck>> */
    private array $checks = [];

    public function __construct(
        private readonly Container $container,
    ) {}

    /**
     * Register a health check class.
     *
     * @param  class-string<ServiceHealthCheck>  $checkClass
     */
    public function register(string $checkClass): void
    {
        if (! in_array($checkClass, $this->checks, true)) {
            $this->checks[] = $checkClass;
        }
    }

    /**
     * Register multiple health check classes.
     *
     * @param  array<class-string<ServiceHealthCheck>>  $checkClasses
     */
    public function registerMany(array $checkClasses): void
    {
        foreach ($checkClasses as $checkClass) {
            $this->register($checkClass);
        }
    }

    /**
     * Run all registered checks and return results sorted by order().
     *
     * @return array<ServiceCheckResult>
     */
    public function runAll(): array
    {
        $results = [];

        foreach ($this->checks as $checkClass) {
            /** @var ServiceHealthCheck $check */
            $check = $this->container->make($checkClass);
            $results[] = ['order' => $check->order(), 'result' => $check->check()];
        }

        usort($results, fn (array $a, array $b): int => $a['order'] <=> $b['order']);

        return array_map(fn (array $item): ServiceCheckResult => $item['result'], $results);
    }

    /**
     * Run all registered checks with Redis caching (30s TTL).
     *
     * Falls back to live probes if cache is unavailable.
     *
     * @return array<ServiceCheckResult>
     */
    public function runAllCached(): array
    {
        try {
            // @codeCoverageIgnoreStart — Untestable in unit context
            return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, fn (): array => $this->runAll());
        } catch (\Throwable) {
            return $this->runAll();
            // @codeCoverageIgnoreEnd
        }
    }

    /**
     * Invalidate the health check cache and run live probes.
     *
     * @return array<ServiceCheckResult>
     */
    public function forceRefresh(): array
    {
        // @codeCoverageIgnoreStart — Untestable in unit context
        Cache::forget(self::CACHE_KEY);

        $results = $this->runAll();

        try {
            Cache::put(self::CACHE_KEY, $results, self::CACHE_TTL);
        } catch (\Throwable) {
            // Cache unavailable — results still returned
        }

        return $results;
        // @codeCoverageIgnoreEnd
    }

    /**
     * Get registered check class names.
     *
     * @return array<class-string<ServiceHealthCheck>>
     */
    public function registered(): array
    {
        return $this->checks;
    }
}
