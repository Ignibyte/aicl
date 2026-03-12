<?php

namespace Aicl\Tests\Feature\Horizon;

use Aicl\Health\Checks\QueueCheck;
use Aicl\Health\ServiceCheckResult;
use Aicl\Health\ServiceStatus;
use Aicl\Horizon\Contracts\JobRepository;
use Aicl\Horizon\Contracts\SupervisorRepository;
use Mockery;
use Tests\TestCase;

class QueueCheckTest extends TestCase
{
    public function test_queue_check_returns_service_check_result(): void
    {
        $jobRepo = Mockery::mock(JobRepository::class);
        $jobRepo->shouldReceive('countPending')->andReturn(0);
        $jobRepo->shouldReceive('countCompleted')->andReturn(10);
        $jobRepo->shouldReceive('countFailed')->andReturn(0);
        app()->instance(JobRepository::class, $jobRepo);

        $supervisorRepo = Mockery::mock(SupervisorRepository::class);
        $supervisorRepo->shouldReceive('all')->andReturn([]);
        app()->instance(SupervisorRepository::class, $supervisorRepo);

        $check = new QueueCheck;
        $result = $check->check();

        $this->assertInstanceOf(ServiceCheckResult::class, $result);
    }

    public function test_queue_check_healthy_when_no_failures(): void
    {
        $jobRepo = Mockery::mock(JobRepository::class);
        $jobRepo->shouldReceive('countPending')->andReturn(5);
        $jobRepo->shouldReceive('countCompleted')->andReturn(100);
        $jobRepo->shouldReceive('countFailed')->andReturn(0);
        app()->instance(JobRepository::class, $jobRepo);

        $supervisorRepo = Mockery::mock(SupervisorRepository::class);
        $supervisorRepo->shouldReceive('all')->andReturn([]);
        app()->instance(SupervisorRepository::class, $supervisorRepo);

        $check = new QueueCheck;
        $result = $check->check();

        $this->assertSame(ServiceStatus::Healthy, $result->status);
        $this->assertSame('Queues', $result->name);
    }

    public function test_queue_check_degraded_when_failed_threshold_exceeded(): void
    {
        config(['aicl.health.failed_jobs_threshold' => 5]);

        $jobRepo = Mockery::mock(JobRepository::class);
        $jobRepo->shouldReceive('countPending')->andReturn(0);
        $jobRepo->shouldReceive('countCompleted')->andReturn(50);
        $jobRepo->shouldReceive('countFailed')->andReturn(10);
        app()->instance(JobRepository::class, $jobRepo);

        $supervisorRepo = Mockery::mock(SupervisorRepository::class);
        $supervisorRepo->shouldReceive('all')->andReturn([]);
        app()->instance(SupervisorRepository::class, $supervisorRepo);

        $check = new QueueCheck;
        $result = $check->check();

        $this->assertSame(ServiceStatus::Degraded, $result->status);
    }

    public function test_queue_check_includes_horizon_details(): void
    {
        $jobRepo = Mockery::mock(JobRepository::class);
        $jobRepo->shouldReceive('countPending')->andReturn(15);
        $jobRepo->shouldReceive('countCompleted')->andReturn(200);
        $jobRepo->shouldReceive('countFailed')->andReturn(0);
        app()->instance(JobRepository::class, $jobRepo);

        $supervisorRepo = Mockery::mock(SupervisorRepository::class);
        $supervisorRepo->shouldReceive('all')->andReturn([]);
        app()->instance(SupervisorRepository::class, $supervisorRepo);

        $check = new QueueCheck;
        $result = $check->check();

        $this->assertArrayHasKey('Pending', $result->details);
        $this->assertArrayHasKey('Completed (recent)', $result->details);
        $this->assertSame('15', $result->details['Pending']);
    }

    public function test_queue_check_order_is_50(): void
    {
        $check = new QueueCheck;

        $this->assertSame(50, $check->order());
    }

    public function test_queue_check_uses_queue_list_icon(): void
    {
        $jobRepo = Mockery::mock(JobRepository::class);
        $jobRepo->shouldReceive('countPending')->andReturn(0);
        $jobRepo->shouldReceive('countCompleted')->andReturn(0);
        $jobRepo->shouldReceive('countFailed')->andReturn(0);
        app()->instance(JobRepository::class, $jobRepo);

        $supervisorRepo = Mockery::mock(SupervisorRepository::class);
        $supervisorRepo->shouldReceive('all')->andReturn([]);
        app()->instance(SupervisorRepository::class, $supervisorRepo);

        $check = new QueueCheck;
        $result = $check->check();

        $this->assertSame('heroicon-o-queue-list', $result->icon);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
