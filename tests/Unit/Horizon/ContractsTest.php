<?php

namespace Aicl\Tests\Unit\Horizon;

use Aicl\Horizon\Contracts\HorizonCommandQueue;
use Aicl\Horizon\Contracts\JobRepository;
use Aicl\Horizon\Contracts\LongWaitDetectedNotification;
use Aicl\Horizon\Contracts\MasterSupervisorRepository;
use Aicl\Horizon\Contracts\MetricsRepository;
use Aicl\Horizon\Contracts\Pausable;
use Aicl\Horizon\Contracts\ProcessRepository;
use Aicl\Horizon\Contracts\Restartable;
use Aicl\Horizon\Contracts\Silenced;
use Aicl\Horizon\Contracts\SupervisorRepository;
use Aicl\Horizon\Contracts\TagRepository;
use Aicl\Horizon\Contracts\Terminable;
use Aicl\Horizon\Contracts\WorkloadRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ContractsTest extends TestCase
{
    #[DataProvider('contractProvider')]
    public function test_contract_exists_and_is_interface(string $contract): void
    {
        $this->assertTrue(interface_exists($contract), "Contract {$contract} should exist");

        $reflection = new \ReflectionClass($contract);
        $this->assertTrue($reflection->isInterface(), "{$contract} should be an interface");
    }

    public static function contractProvider(): array
    {
        return [
            'JobRepository' => [JobRepository::class],
            'MetricsRepository' => [MetricsRepository::class],
            'TagRepository' => [TagRepository::class],
            'WorkloadRepository' => [WorkloadRepository::class],
            'SupervisorRepository' => [SupervisorRepository::class],
            'MasterSupervisorRepository' => [MasterSupervisorRepository::class],
            'ProcessRepository' => [ProcessRepository::class],
            'HorizonCommandQueue' => [HorizonCommandQueue::class],
            'Pausable' => [Pausable::class],
            'Restartable' => [Restartable::class],
            'Terminable' => [Terminable::class],
            'Silenced' => [Silenced::class],
            'LongWaitDetectedNotification' => [LongWaitDetectedNotification::class],
        ];
    }

    public function test_job_repository_has_expected_methods(): void
    {
        $reflection = new \ReflectionClass(JobRepository::class);
        $methods = array_map(fn ($m) => $m->getName(), $reflection->getMethods());

        $expected = [
            'nextJobId', 'totalRecent', 'totalFailed',
            'getRecent', 'getFailed', 'getPending', 'getCompleted', 'getSilenced',
            'countRecent', 'countFailed', 'countPending', 'countCompleted', 'countSilenced',
            'pushed', 'reserved', 'released', 'remember', 'migrated', 'completed',
            'trimRecentJobs', 'trimFailedJobs', 'trimMonitoredJobs',
            'findFailed', 'failed', 'storeRetryReference', 'deleteFailed',
        ];

        foreach ($expected as $method) {
            $this->assertContains($method, $methods, "JobRepository missing method: {$method}");
        }
    }

    public function test_tag_repository_has_expected_methods(): void
    {
        $reflection = new \ReflectionClass(TagRepository::class);
        $methods = array_map(fn ($m) => $m->getName(), $reflection->getMethods());

        $expected = ['monitoring', 'monitored', 'monitor', 'stopMonitoring', 'add', 'addTemporary', 'count', 'jobs', 'paginate', 'forget'];

        foreach ($expected as $method) {
            $this->assertContains($method, $methods, "TagRepository missing method: {$method}");
        }
    }

    public function test_metrics_repository_interface_exists(): void
    {
        $this->assertTrue(interface_exists(MetricsRepository::class));
    }

    public function test_workload_repository_interface_exists(): void
    {
        $this->assertTrue(interface_exists(WorkloadRepository::class));
    }
}
