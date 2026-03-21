<?php

namespace Aicl\Tests\Unit\Horizon;

use Aicl\Horizon\AutoScaler;
use Aicl\Horizon\Contracts\HorizonCommandQueue;
use Aicl\Horizon\Contracts\JobRepository;
use Aicl\Horizon\Contracts\LongWaitDetectedNotification;
use Aicl\Horizon\Contracts\MasterSupervisorRepository;
use Aicl\Horizon\Contracts\MetricsRepository;
use Aicl\Horizon\Contracts\ProcessRepository;
use Aicl\Horizon\Contracts\SupervisorRepository;
use Aicl\Horizon\Contracts\TagRepository;
use Aicl\Horizon\Contracts\WorkloadRepository;
use Aicl\Horizon\Listeners\TrimFailedJobs;
use Aicl\Horizon\Listeners\TrimMonitoredJobs;
use Aicl\Horizon\Listeners\TrimRecentJobs;
use Aicl\Horizon\Lock;
use Aicl\Horizon\Notifications\LongWaitDetected;
use Aicl\Horizon\RedisHorizonCommandQueue;
use Aicl\Horizon\Repositories\RedisJobRepository;
use Aicl\Horizon\Repositories\RedisMasterSupervisorRepository;
use Aicl\Horizon\Repositories\RedisMetricsRepository;
use Aicl\Horizon\Repositories\RedisProcessRepository;
use Aicl\Horizon\Repositories\RedisSupervisorRepository;
use Aicl\Horizon\Repositories\RedisTagRepository;
use Aicl\Horizon\Repositories\RedisWorkloadRepository;
use Aicl\Horizon\ServiceBindings;
use Aicl\Horizon\Stopwatch;
use PHPUnit\Framework\TestCase;

class ServiceBindingsTest extends TestCase
{
    private object $provider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->provider = new class
        {
            use ServiceBindings;
        };
    }

    public function test_service_bindings_contains_all_repository_contracts(): void
    {
        /** @phpstan-ignore-next-line */
        $bindings = $this->provider->serviceBindings;

        $this->assertArrayHasKey(JobRepository::class, $bindings);
        $this->assertArrayHasKey(MasterSupervisorRepository::class, $bindings);
        $this->assertArrayHasKey(MetricsRepository::class, $bindings);
        $this->assertArrayHasKey(ProcessRepository::class, $bindings);
        $this->assertArrayHasKey(SupervisorRepository::class, $bindings);
        $this->assertArrayHasKey(TagRepository::class, $bindings);
        $this->assertArrayHasKey(WorkloadRepository::class, $bindings);
    }

    public function test_repository_bindings_map_to_redis_implementations(): void
    {
        /** @phpstan-ignore-next-line */
        $bindings = $this->provider->serviceBindings;

        $this->assertSame(RedisJobRepository::class, $bindings[JobRepository::class]);
        $this->assertSame(RedisMasterSupervisorRepository::class, $bindings[MasterSupervisorRepository::class]);
        $this->assertSame(RedisMetricsRepository::class, $bindings[MetricsRepository::class]);
        $this->assertSame(RedisProcessRepository::class, $bindings[ProcessRepository::class]);
        $this->assertSame(RedisSupervisorRepository::class, $bindings[SupervisorRepository::class]);
        $this->assertSame(RedisTagRepository::class, $bindings[TagRepository::class]);
        $this->assertSame(RedisWorkloadRepository::class, $bindings[WorkloadRepository::class]);
    }

    public function test_command_queue_binding(): void
    {
        /** @phpstan-ignore-next-line */
        $bindings = $this->provider->serviceBindings;

        $this->assertArrayHasKey(HorizonCommandQueue::class, $bindings);
        $this->assertSame(RedisHorizonCommandQueue::class, $bindings[HorizonCommandQueue::class]);
    }

    public function test_notification_binding(): void
    {
        /** @phpstan-ignore-next-line */
        $bindings = $this->provider->serviceBindings;

        $this->assertArrayHasKey(LongWaitDetectedNotification::class, $bindings);
        $this->assertSame(LongWaitDetected::class, $bindings[LongWaitDetectedNotification::class]);
    }

    public function test_singleton_bindings_for_services(): void
    {
        /** @phpstan-ignore-next-line */
        $bindings = $this->provider->serviceBindings;

        // Numeric-keyed entries are singletons (no contract → implementation mapping)
        $singletons = array_values(array_filter($bindings, fn ($v, $k) => is_numeric($k), ARRAY_FILTER_USE_BOTH));

        $this->assertContains(AutoScaler::class, $singletons);
        $this->assertContains(TrimRecentJobs::class, $singletons);
        $this->assertContains(TrimFailedJobs::class, $singletons);
        $this->assertContains(TrimMonitoredJobs::class, $singletons);
        $this->assertContains(Lock::class, $singletons);
        $this->assertContains(Stopwatch::class, $singletons);
    }
}
