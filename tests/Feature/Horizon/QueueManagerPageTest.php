<?php

namespace Aicl\Tests\Feature\Horizon;

use Aicl\Filament\Pages\QueueManager;
use Aicl\Horizon\Contracts\JobRepository;
use Aicl\Horizon\Contracts\MetricsRepository;
use Aicl\Horizon\Contracts\SupervisorRepository;
use Aicl\Horizon\Contracts\WorkloadRepository;
use Mockery;
use Tests\TestCase;

class QueueManagerPageTest extends TestCase
{
    public function test_queue_manager_page_exists(): void
    {
        $this->assertTrue(class_exists(QueueManager::class));
    }

    public function test_queue_manager_slug(): void
    {
        $this->assertSame('queue-manager', QueueManager::getSlug());
    }

    public function test_queue_manager_uses_correct_view(): void
    {
        $page = new QueueManager;

        $reflection = new \ReflectionProperty($page, 'view');
        $this->assertSame('aicl::filament.pages.queue-manager', $reflection->getValue($page));
    }

    public function test_queue_manager_has_active_tab_property(): void
    {
        $page = new QueueManager;

        $this->assertSame('overview', $page->activeTab);
    }

    public function test_get_queue_stats_returns_expected_structure(): void
    {
        $jobRepo = Mockery::mock(JobRepository::class);
        $jobRepo->shouldReceive('countPending')->andReturn(5);
        $jobRepo->shouldReceive('countFailed')->andReturn(2);
        app()->instance(JobRepository::class, $jobRepo);

        $workloadRepo = Mockery::mock(WorkloadRepository::class);
        $workloadRepo->shouldReceive('get')->andReturn([]);
        app()->instance(WorkloadRepository::class, $workloadRepo);

        $metricsRepo = Mockery::mock(MetricsRepository::class);
        $metricsRepo->shouldReceive('snapshotsForQueue')->with('default')->andReturn([]);
        app()->instance(MetricsRepository::class, $metricsRepo);

        $page = new QueueManager;
        $stats = $page->getQueueStats();

        $this->assertArrayHasKey('pending', $stats);
        $this->assertArrayHasKey('failed', $stats);
        $this->assertArrayHasKey('last_failed', $stats);
        $this->assertArrayHasKey('jobs_per_minute', $stats);
        $this->assertArrayHasKey('total_processes', $stats);
        $this->assertArrayHasKey('workload', $stats);
    }

    public function test_get_queue_stats_uses_horizon_data_when_available(): void
    {
        $jobRepo = Mockery::mock(JobRepository::class);
        $jobRepo->shouldReceive('countPending')->andReturn(42);
        $jobRepo->shouldReceive('countFailed')->andReturn(7);
        app()->instance(JobRepository::class, $jobRepo);

        $workloadRepo = Mockery::mock(WorkloadRepository::class);
        $workloadRepo->shouldReceive('get')->andReturn([
            ['name' => 'default', 'length' => 10, 'wait' => 5, 'processes' => 3],
        ]);
        app()->instance(WorkloadRepository::class, $workloadRepo);

        $metricsRepo = Mockery::mock(MetricsRepository::class);
        $metricsRepo->shouldReceive('snapshotsForQueue')->andReturn([]);
        app()->instance(MetricsRepository::class, $metricsRepo);

        $page = new QueueManager;
        $stats = $page->getQueueStats();

        $this->assertSame(42, $stats['pending']);
        $this->assertSame(3, $stats['total_processes']);
    }

    public function test_get_queue_stats_includes_jobs_per_minute(): void
    {
        $snapshot = (object) ['throughput' => 8.5];

        $jobRepo = Mockery::mock(JobRepository::class);
        $jobRepo->shouldReceive('countPending')->andReturn(0);
        $jobRepo->shouldReceive('countFailed')->andReturn(0);
        app()->instance(JobRepository::class, $jobRepo);

        $workloadRepo = Mockery::mock(WorkloadRepository::class);
        $workloadRepo->shouldReceive('get')->andReturn([]);
        app()->instance(WorkloadRepository::class, $workloadRepo);

        $metricsRepo = Mockery::mock(MetricsRepository::class);
        $metricsRepo->shouldReceive('snapshotsForQueue')->with('default')->andReturn([$snapshot]);
        app()->instance(MetricsRepository::class, $metricsRepo);

        $page = new QueueManager;
        $stats = $page->getQueueStats();

        $this->assertSame(8.5, $stats['jobs_per_minute']);
    }

    public function test_get_supervisors_returns_array(): void
    {
        $supervisorRepo = Mockery::mock(SupervisorRepository::class);
        $supervisorRepo->shouldReceive('all')->andReturn([]);
        app()->instance(SupervisorRepository::class, $supervisorRepo);

        $page = new QueueManager;
        $result = $page->getSupervisors();

        $this->assertIsArray($result);
    }

    public function test_get_supervisors_returns_empty_when_horizon_disabled(): void
    {
        config(['aicl.features.horizon' => false]);

        $page = new QueueManager;
        $result = $page->getSupervisors();

        $this->assertSame([], $result);
    }

    public function test_is_horizon_available_returns_true_when_repo_bound(): void
    {
        $jobRepo = Mockery::mock(JobRepository::class);
        app()->instance(JobRepository::class, $jobRepo);
        config(['aicl.features.horizon' => true]);

        $page = new QueueManager;
        $this->assertTrue($page->isHorizonAvailable());
    }

    public function test_is_horizon_available_returns_false_when_disabled(): void
    {
        config(['aicl.features.horizon' => false]);

        $page = new QueueManager;
        $this->assertFalse($page->isHorizonAvailable());
    }

    public function test_get_queue_driver_returns_configured_driver(): void
    {
        config(['queue.default' => 'redis', 'queue.connections.redis.driver' => 'redis']);

        $page = new QueueManager;
        $this->assertSame('redis', $page->getQueueDriver());
    }

    public function test_get_available_tabs_shows_all_tabs_when_horizon_available(): void
    {
        $jobRepo = Mockery::mock(JobRepository::class);
        app()->instance(JobRepository::class, $jobRepo);
        config(['aicl.features.horizon' => true]);

        $page = new QueueManager;
        $tabs = $page->getAvailableTabs();

        $this->assertArrayHasKey('overview', $tabs);
        $this->assertArrayHasKey('recent', $tabs);
        $this->assertArrayHasKey('pending', $tabs);
        $this->assertArrayHasKey('completed', $tabs);
        $this->assertArrayHasKey('failed-jobs', $tabs);
        $this->assertArrayHasKey('batches', $tabs);
        $this->assertArrayHasKey('metrics', $tabs);
        $this->assertArrayHasKey('workload', $tabs);
        $this->assertArrayHasKey('supervisors', $tabs);
        $this->assertArrayHasKey('monitoring', $tabs);
        $this->assertCount(10, $tabs);
    }

    public function test_get_available_tabs_shows_reduced_tabs_when_horizon_unavailable(): void
    {
        config(['aicl.features.horizon' => false]);

        $page = new QueueManager;
        $tabs = $page->getAvailableTabs();

        $this->assertArrayHasKey('overview', $tabs);
        $this->assertArrayHasKey('failed-jobs', $tabs);
        $this->assertArrayHasKey('batches', $tabs);
        $this->assertArrayNotHasKey('recent', $tabs);
        $this->assertArrayNotHasKey('pending', $tabs);
        $this->assertArrayNotHasKey('completed', $tabs);
        $this->assertArrayNotHasKey('metrics', $tabs);
        $this->assertArrayNotHasKey('workload', $tabs);
        $this->assertArrayNotHasKey('supervisors', $tabs);
        $this->assertArrayNotHasKey('monitoring', $tabs);
        $this->assertCount(3, $tabs);
    }

    public function test_can_access_returns_false_for_guests(): void
    {
        $this->assertFalse(QueueManager::canAccess());
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
