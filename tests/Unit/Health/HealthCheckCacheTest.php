<?php

namespace Aicl\Tests\Unit\Health;

use Aicl\Health\HealthCheckRegistry;
use Aicl\Health\ServiceCheckResult;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class HealthCheckCacheTest extends TestCase
{
    private HealthCheckRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registry = app(HealthCheckRegistry::class);
        Cache::forget('aicl:health_checks');
    }

    protected function tearDown(): void
    {
        Cache::forget('aicl:health_checks');
        parent::tearDown();
    }

    public function test_run_all_cached_returns_results(): void
    {
        $results = $this->registry->runAllCached();

    }

    public function test_run_all_cached_populates_cache(): void
    {
        $this->assertNull(Cache::get('aicl:health_checks'));

        $this->registry->runAllCached();

        $this->assertNotNull(Cache::get('aicl:health_checks'));
    }

    public function test_run_all_cached_returns_cached_results_on_second_call(): void
    {
        $first = $this->registry->runAllCached();
        $second = $this->registry->runAllCached();

        $this->assertEquals($first, $second);
    }

    public function test_force_refresh_clears_cache_and_returns_fresh_results(): void
    {
        $this->registry->runAllCached();
        $this->assertNotNull(Cache::get('aicl:health_checks'));

        $results = $this->registry->forceRefresh();

        // Cache is repopulated after force refresh
        $this->assertNotNull(Cache::get('aicl:health_checks'));
    }

    public function test_force_refresh_returns_array_of_service_check_results(): void
    {
        $results = $this->registry->forceRefresh();

        foreach ($results as $result) {
            $this->assertInstanceOf(ServiceCheckResult::class, $result);
        }
    }

    public function test_run_all_cached_matches_run_all_on_cache_miss(): void
    {
        $cached = $this->registry->runAllCached();
        Cache::forget('aicl:health_checks');
        $fresh = $this->registry->runAll();

        // Both should contain same number of results
        $this->assertCount(count($fresh), $cached);
    }
}
