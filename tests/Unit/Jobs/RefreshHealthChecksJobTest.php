<?php

declare(strict_types=1);

namespace Aicl\Tests\Unit\Jobs;

use Aicl\Health\HealthCheckRegistry;
use Aicl\Health\ServiceCheckResult;
use Aicl\Health\ServiceStatus;
use Aicl\Jobs\RefreshHealthChecksJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Tests the RefreshHealthChecksJob including queue interface,
 * cache storage of results, and error handling.
 */
class RefreshHealthChecksJobTest extends TestCase
{
    public function test_job_stores_results_in_redis_cache(): void
    {
        $results = [
            ServiceCheckResult::healthy('database', 'heroicon-o-circle-stack'),
            ServiceCheckResult::down('elasticsearch', 'heroicon-o-magnifying-glass', [], 'Connection refused'),
        ];

        $registry = $this->createMock(HealthCheckRegistry::class);
        $registry->expects($this->once())
            ->method('runAll')
            ->willReturn($results);

        $job = new RefreshHealthChecksJob;
        $job->handle($registry);

        $cached = Cache::get('aicl:health_check_results');
        $this->assertNotNull($cached);

        $unserialized = unserialize($cached);
        $this->assertCount(2, $unserialized);
        $this->assertSame('database', $unserialized[0]->name);
        $this->assertSame(ServiceStatus::Healthy, $unserialized[0]->status);
        $this->assertSame('elasticsearch', $unserialized[1]->name);
        $this->assertSame(ServiceStatus::Down, $unserialized[1]->status);
    }

    public function test_job_logs_debug_message(): void
    {
        Log::spy();

        $registry = $this->createMock(HealthCheckRegistry::class);
        $registry->method('runAll')->willReturn([]);

        $job = new RefreshHealthChecksJob;
        $job->handle($registry);

        /** @phpstan-ignore-next-line */
        Log::shouldHaveReceived('debug')
            ->withArgs(fn ($message, $context) => $message === 'Health checks refreshed' && $context['count'] === 0)
            ->once();
    }

    public function test_job_handles_empty_results(): void
    {
        $registry = $this->createMock(HealthCheckRegistry::class);
        $registry->method('runAll')->willReturn([]);

        $job = new RefreshHealthChecksJob;
        $job->handle($registry);

        $cached = Cache::get('aicl:health_check_results');
        $unserialized = unserialize($cached);
        $this->assertCount(0, $unserialized);
    }

    public function test_job_is_queueable(): void
    {
        $job = new RefreshHealthChecksJob;

        $this->assertInstanceOf(ShouldQueue::class, $job);
    }
}
