<?php

namespace Aicl\Tests\Unit\Health;

use Aicl\Health\Checks\QueueCheck;
use Aicl\Health\ServiceStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Mockery;
use Tests\TestCase;

class QueueCheckTest extends TestCase
{
    private QueueCheck $check;

    protected function setUp(): void
    {
        parent::setUp();

        // These tests verify the direct-Redis fallback path (Horizon disabled).
        // Horizon-aware path is tested in Feature\Horizon\QueueCheckTest.
        config(['aicl.features.horizon' => false]);

        $this->check = new QueueCheck;
    }

    // ── Healthy ──────────────────────────────────────────────

    public function test_returns_healthy_with_queue_sizes_and_failed_count(): void
    {
        config([
            'aicl.health.queues' => ['default', 'high'],
            'aicl.health.failed_jobs_threshold' => 10,
            'database.redis.options.prefix' => 'test_',
        ]);

        $connection = Mockery::mock();
        $connection->shouldReceive('llen')
            /** @phpstan-ignore-next-line */
            ->with('test_queues:default')
            ->andReturn(5);
        $connection->shouldReceive('llen')
            /** @phpstan-ignore-next-line */
            ->with('test_queues:high')
            ->andReturn(2);

        Redis::shouldReceive('connection')->andReturn($connection);

        // Mock failed jobs count via DB
        DB::shouldReceive('selectOne')
            ->with('SELECT count(*) as count FROM failed_jobs')
            ->andReturn((object) ['count' => 3]);

        $result = $this->check->check();

        $this->assertSame(ServiceStatus::Healthy, $result->status);
        $this->assertSame('Queues', $result->name);
        $this->assertSame('heroicon-o-queue-list', $result->icon);
        $this->assertNull($result->error);
        $this->assertSame('5 pending', $result->details['Queue: default']);
        $this->assertSame('2 pending', $result->details['Queue: high']);
        $this->assertSame('3', $result->details['Failed Jobs']);
    }

    public function test_returns_healthy_when_failed_below_threshold(): void
    {
        config([
            'aicl.health.queues' => ['default'],
            'aicl.health.failed_jobs_threshold' => 10,
            'database.redis.options.prefix' => '',
        ]);

        $connection = Mockery::mock();
        /** @phpstan-ignore-next-line */
        $connection->shouldReceive('llen')->andReturn(0);

        Redis::shouldReceive('connection')->andReturn($connection);

        DB::shouldReceive('selectOne')
            ->andReturn((object) ['count' => 9]);

        $result = $this->check->check();

        $this->assertSame(ServiceStatus::Healthy, $result->status);
    }

    // ── Degraded ─────────────────────────────────────────────

    public function test_returns_degraded_when_failed_jobs_exceed_threshold(): void
    {
        config([
            'aicl.health.queues' => ['default'],
            'aicl.health.failed_jobs_threshold' => 10,
            'database.redis.options.prefix' => '',
        ]);

        $connection = Mockery::mock();
        /** @phpstan-ignore-next-line */
        $connection->shouldReceive('llen')->andReturn(0);

        Redis::shouldReceive('connection')->andReturn($connection);

        DB::shouldReceive('selectOne')
            ->andReturn((object) ['count' => 15]);

        $result = $this->check->check();

        $this->assertSame(ServiceStatus::Degraded, $result->status);
        /** @phpstan-ignore-next-line */
        $this->assertStringContainsString('15', $result->error);
        /** @phpstan-ignore-next-line */
        $this->assertStringContainsString('10', $result->error);
    }

    public function test_returns_degraded_when_failed_jobs_equal_threshold(): void
    {
        config([
            'aicl.health.queues' => ['default'],
            'aicl.health.failed_jobs_threshold' => 10,
            'database.redis.options.prefix' => '',
        ]);

        $connection = Mockery::mock();
        /** @phpstan-ignore-next-line */
        $connection->shouldReceive('llen')->andReturn(0);

        Redis::shouldReceive('connection')->andReturn($connection);

        DB::shouldReceive('selectOne')
            ->andReturn((object) ['count' => 10]);

        $result = $this->check->check();

        $this->assertSame(ServiceStatus::Degraded, $result->status);
    }

    // ── Down ─────────────────────────────────────────────────

    public function test_returns_down_when_redis_connection_fails(): void
    {
        config([
            'aicl.health.queues' => ['default'],
            'database.redis.options.prefix' => '',
        ]);

        Redis::shouldReceive('connection')
            ->once()
            ->andThrow(new \RuntimeException('Connection refused'));

        $result = $this->check->check();

        $this->assertSame(ServiceStatus::Down, $result->status);
        $this->assertSame('Queues', $result->name);
        $this->assertSame('Connection refused', $result->error);
    }

    // ── Default config ───────────────────────────────────────

    public function test_uses_default_queues_when_not_configured(): void
    {
        // Ensure the config key is absent so the default kicks in
        $config = app('config');
        $aicl = $config->get('aicl', []);
        unset($aicl['health']['queues']);
        $config->set('aicl', $aicl);

        config(['database.redis.options.prefix' => '']);

        $connection = Mockery::mock();
        $connection->shouldReceive('llen')
            /** @phpstan-ignore-next-line */
            ->with('queues:default')
            ->andReturn(0);
        $connection->shouldReceive('llen')
            /** @phpstan-ignore-next-line */
            ->with('queues:notifications')
            ->andReturn(0);
        $connection->shouldReceive('llen')
            /** @phpstan-ignore-next-line */
            ->with('queues:high')
            ->andReturn(0);
        $connection->shouldReceive('llen')
            /** @phpstan-ignore-next-line */
            ->with('queues:low')
            ->andReturn(0);

        Redis::shouldReceive('connection')->andReturn($connection);

        DB::shouldReceive('selectOne')
            ->andReturn((object) ['count' => 0]);

        $result = $this->check->check();

        $this->assertSame(ServiceStatus::Healthy, $result->status);
        $this->assertArrayHasKey('Queue: default', $result->details);
        $this->assertArrayHasKey('Queue: notifications', $result->details);
        $this->assertArrayHasKey('Queue: high', $result->details);
        $this->assertArrayHasKey('Queue: low', $result->details);
    }

    public function test_handles_missing_failed_jobs_table(): void
    {
        config([
            'aicl.health.queues' => ['default'],
            'aicl.health.failed_jobs_threshold' => 10,
            'database.redis.options.prefix' => '',
        ]);

        $connection = Mockery::mock();
        /** @phpstan-ignore-next-line */
        $connection->shouldReceive('llen')->andReturn(0);

        Redis::shouldReceive('connection')->andReturn($connection);

        DB::shouldReceive('selectOne')
            ->andThrow(new \RuntimeException('relation "failed_jobs" does not exist'));

        $result = $this->check->check();

        // Should still be healthy with failed jobs count of 0
        $this->assertSame(ServiceStatus::Healthy, $result->status);
        $this->assertSame('0', $result->details['Failed Jobs']);
    }

    // ── order() ──────────────────────────────────────────────

    public function test_order_returns_50(): void
    {
        $this->assertSame(50, $this->check->order());
    }
}
