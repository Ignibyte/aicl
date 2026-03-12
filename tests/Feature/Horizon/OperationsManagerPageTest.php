<?php

namespace Aicl\Tests\Feature\Horizon;

use Aicl\Filament\Pages\OperationsManager;
use Aicl\Horizon\Contracts\JobRepository;
use Aicl\Horizon\Contracts\MetricsRepository;
use Aicl\Horizon\Contracts\SupervisorRepository;
use Aicl\Horizon\Contracts\WorkloadRepository;
use Mockery;
use Tests\TestCase;

class OperationsManagerPageTest extends TestCase
{
    public function test_operations_manager_page_exists(): void
    {
        $this->assertTrue(class_exists(OperationsManager::class));
    }

    public function test_operations_manager_slug(): void
    {
        $this->assertSame('operations-manager', OperationsManager::getSlug());
    }

    public function test_operations_manager_uses_correct_view(): void
    {
        $page = new OperationsManager;

        $reflection = new \ReflectionProperty($page, 'view');
        $this->assertSame('aicl::filament.pages.operations-manager', $reflection->getValue($page));
    }

    public function test_operations_manager_has_active_tab_property(): void
    {
        $page = new OperationsManager;

        $this->assertSame('overview', $page->activeTab);
    }

    public function test_operations_manager_has_active_section_property(): void
    {
        $page = new OperationsManager;

        $this->assertSame('queues', $page->activeSection);
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

        $page = new OperationsManager;
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

        $page = new OperationsManager;
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

        $page = new OperationsManager;
        $stats = $page->getQueueStats();

        $this->assertSame(8.5, $stats['jobs_per_minute']);
    }

    public function test_get_supervisors_returns_array(): void
    {
        $supervisorRepo = Mockery::mock(SupervisorRepository::class);
        $supervisorRepo->shouldReceive('all')->andReturn([]);
        app()->instance(SupervisorRepository::class, $supervisorRepo);

        $page = new OperationsManager;
        $result = $page->getSupervisors();

        $this->assertIsArray($result);
    }

    public function test_get_supervisors_returns_empty_when_horizon_disabled(): void
    {
        config(['aicl.features.horizon' => false]);

        $page = new OperationsManager;
        $result = $page->getSupervisors();

        $this->assertSame([], $result);
    }

    public function test_is_horizon_available_returns_true_when_repo_bound(): void
    {
        $jobRepo = Mockery::mock(JobRepository::class);
        app()->instance(JobRepository::class, $jobRepo);
        config(['aicl.features.horizon' => true]);

        $page = new OperationsManager;
        $this->assertTrue($page->isHorizonAvailable());
    }

    public function test_is_horizon_available_returns_false_when_disabled(): void
    {
        config(['aicl.features.horizon' => false]);

        $page = new OperationsManager;
        $this->assertFalse($page->isHorizonAvailable());
    }

    public function test_get_queue_driver_returns_configured_driver(): void
    {
        config(['queue.default' => 'redis', 'queue.connections.redis.driver' => 'redis']);

        $page = new OperationsManager;
        $this->assertSame('redis', $page->getQueueDriver());
    }

    public function test_get_queue_tabs_shows_all_tabs_when_horizon_available(): void
    {
        $jobRepo = Mockery::mock(JobRepository::class);
        app()->instance(JobRepository::class, $jobRepo);
        config(['aicl.features.horizon' => true]);

        $page = new OperationsManager;
        $tabs = $page->getQueueTabs();

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

    public function test_get_queue_tabs_shows_reduced_tabs_when_horizon_unavailable(): void
    {
        config(['aicl.features.horizon' => false]);

        $page = new OperationsManager;
        $tabs = $page->getQueueTabs();

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

    // ── Scheduler Section ────────────────────────────────────

    public function test_get_scheduler_tabs_returns_expected_tabs(): void
    {
        $page = new OperationsManager;
        $tabs = $page->getSchedulerTabs();

        $this->assertArrayHasKey('registered', $tabs);
        $this->assertArrayHasKey('history', $tabs);
        $this->assertArrayHasKey('schedule-failures', $tabs);
        $this->assertCount(3, $tabs);
    }

    public function test_get_scheduler_stats_returns_expected_structure(): void
    {
        $page = new OperationsManager;
        $stats = $page->getSchedulerStats();

        $this->assertArrayHasKey('total_registered', $stats);
        $this->assertArrayHasKey('last_run_at', $stats);
        $this->assertArrayHasKey('failed_24h', $stats);
        $this->assertArrayHasKey('success_rate_24h', $stats);
    }

    public function test_get_registered_tasks_returns_array(): void
    {
        $page = new OperationsManager;
        $tasks = $page->getRegisteredTasks();

        $this->assertIsArray($tasks);
    }

    // ── Notification Section ────────────────────────────────────

    public function test_get_notification_tabs_returns_expected_tabs(): void
    {
        $page = new OperationsManager;
        $tabs = $page->getNotificationTabs();

        $this->assertArrayHasKey('delivery-health', $tabs);
        $this->assertArrayHasKey('failed-deliveries', $tabs);
        $this->assertCount(2, $tabs);
    }

    public function test_get_notification_delivery_health_returns_array(): void
    {
        $page = new OperationsManager;
        $health = $page->getNotificationDeliveryHealth();

        $this->assertIsArray($health);
    }

    public function test_get_notification_queue_depth_returns_integer(): void
    {
        $page = new OperationsManager;
        $depth = $page->getNotificationQueueDepth();

        $this->assertIsInt($depth);
    }

    public function test_get_stuck_deliveries_returns_integer(): void
    {
        $page = new OperationsManager;
        $stuck = $page->getStuckDeliveries();

        $this->assertIsInt($stuck);
    }

    // ── Access Control ────────────────────────────────────

    public function test_can_access_returns_false_for_guests(): void
    {
        $this->assertFalse(OperationsManager::canAccess());
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
