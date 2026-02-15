<?php

namespace Aicl\Tests\Unit\Health;

use Aicl\Health\Checks\RedisCheck;
use Aicl\Health\ServiceStatus;
use Illuminate\Support\Facades\Redis;
use Mockery;
use Tests\TestCase;

class RedisCheckTest extends TestCase
{
    private RedisCheck $check;

    protected function setUp(): void
    {
        parent::setUp();

        $this->check = new RedisCheck;
    }

    // ── Healthy ──────────────────────────────────────────────

    public function test_returns_healthy_when_redis_available(): void
    {
        $connection = Mockery::mock();
        $connection->shouldReceive('ping')->once();
        $connection->shouldReceive('info')->once()->andReturn([
            'Server' => [
                'redis_version' => '7.2.0',
                'uptime_in_days' => '10',
            ],
            'Memory' => [
                'used_memory_human' => '2.50M',
                'used_memory' => 2621440,
                'maxmemory' => 104857600,
            ],
            'Clients' => [
                'connected_clients' => '5',
            ],
            'Keyspace' => [
                'db0' => 'keys=100,expires=50',
            ],
        ]);

        Redis::shouldReceive('connection')->once()->andReturn($connection);

        $result = $this->check->check();

        $this->assertSame(ServiceStatus::Healthy, $result->status);
        $this->assertSame('Redis', $result->name);
        $this->assertSame('heroicon-o-server-stack', $result->icon);
        $this->assertNull($result->error);
    }

    public function test_healthy_includes_version(): void
    {
        $connection = Mockery::mock();
        $connection->shouldReceive('ping');
        $connection->shouldReceive('info')->andReturn([
            'Server' => ['redis_version' => '7.2.0', 'uptime_in_days' => '5'],
            'Memory' => ['used_memory_human' => '1M', 'maxmemory' => 0],
            'Clients' => ['connected_clients' => '3'],
            'Keyspace' => [],
        ]);

        Redis::shouldReceive('connection')->andReturn($connection);

        $result = $this->check->check();

        $this->assertArrayHasKey('Version', $result->details);
        $this->assertSame('7.2.0', $result->details['Version']);
    }

    public function test_healthy_includes_memory_used(): void
    {
        $connection = Mockery::mock();
        $connection->shouldReceive('ping');
        $connection->shouldReceive('info')->andReturn([
            'Server' => ['redis_version' => '7.0', 'uptime_in_days' => '1'],
            'Memory' => ['used_memory_human' => '3.5M', 'maxmemory' => 0],
            'Clients' => ['connected_clients' => '2'],
            'Keyspace' => [],
        ]);

        Redis::shouldReceive('connection')->andReturn($connection);

        $result = $this->check->check();

        $this->assertSame('3.5M', $result->details['Memory Used']);
    }

    public function test_healthy_includes_connected_clients(): void
    {
        $connection = Mockery::mock();
        $connection->shouldReceive('ping');
        $connection->shouldReceive('info')->andReturn([
            'Server' => ['redis_version' => '7.0', 'uptime_in_days' => '1'],
            'Memory' => ['used_memory_human' => '1M', 'maxmemory' => 0],
            'Clients' => ['connected_clients' => '8'],
            'Keyspace' => [],
        ]);

        Redis::shouldReceive('connection')->andReturn($connection);

        $result = $this->check->check();

        $this->assertSame('8', $result->details['Connected Clients']);
    }

    public function test_healthy_includes_total_keys(): void
    {
        $connection = Mockery::mock();
        $connection->shouldReceive('ping');
        $connection->shouldReceive('info')->andReturn([
            'Server' => ['redis_version' => '7.0', 'uptime_in_days' => '1'],
            'Memory' => ['used_memory_human' => '1M', 'maxmemory' => 0],
            'Clients' => ['connected_clients' => '1'],
            'Keyspace' => [
                'db0' => 'keys=100,expires=50',
                'db1' => 'keys=200,expires=100',
            ],
        ]);

        Redis::shouldReceive('connection')->andReturn($connection);

        $result = $this->check->check();

        $this->assertSame('300', $result->details['Total Keys']);
    }

    public function test_healthy_includes_uptime(): void
    {
        $connection = Mockery::mock();
        $connection->shouldReceive('ping');
        $connection->shouldReceive('info')->andReturn([
            'Server' => ['redis_version' => '7.0', 'uptime_in_days' => '42'],
            'Memory' => ['used_memory_human' => '1M', 'maxmemory' => 0],
            'Clients' => ['connected_clients' => '1'],
            'Keyspace' => [],
        ]);

        Redis::shouldReceive('connection')->andReturn($connection);

        $result = $this->check->check();

        $this->assertSame('42 days', $result->details['Uptime']);
    }

    // ── Degraded ─────────────────────────────────────────────

    public function test_returns_degraded_when_memory_above_90_percent(): void
    {
        $connection = Mockery::mock();
        $connection->shouldReceive('ping');
        $connection->shouldReceive('info')->andReturn([
            'Server' => ['redis_version' => '7.0', 'uptime_in_days' => '1'],
            'Memory' => [
                'used_memory_human' => '950M',
                'used_memory' => 996147200,    // ~950MB
                'maxmemory' => 1073741824,     // 1GB
            ],
            'Clients' => ['connected_clients' => '3'],
            'Keyspace' => [],
        ]);

        Redis::shouldReceive('connection')->andReturn($connection);

        $result = $this->check->check();

        $this->assertSame(ServiceStatus::Degraded, $result->status);
        $this->assertSame('Memory usage above 90% of maxmemory.', $result->error);
    }

    public function test_healthy_when_memory_exactly_at_90_percent(): void
    {
        $maxmemory = 1000;
        $usedMemory = 900; // exactly 90%

        $connection = Mockery::mock();
        $connection->shouldReceive('ping');
        $connection->shouldReceive('info')->andReturn([
            'Server' => ['redis_version' => '7.0', 'uptime_in_days' => '1'],
            'Memory' => [
                'used_memory_human' => '900B',
                'used_memory' => $usedMemory,
                'maxmemory' => $maxmemory,
            ],
            'Clients' => ['connected_clients' => '1'],
            'Keyspace' => [],
        ]);

        Redis::shouldReceive('connection')->andReturn($connection);

        $result = $this->check->check();

        $this->assertSame(ServiceStatus::Healthy, $result->status);
    }

    public function test_healthy_when_maxmemory_is_zero(): void
    {
        $connection = Mockery::mock();
        $connection->shouldReceive('ping');
        $connection->shouldReceive('info')->andReturn([
            'Server' => ['redis_version' => '7.0', 'uptime_in_days' => '1'],
            'Memory' => [
                'used_memory_human' => '500M',
                'used_memory' => 524288000,
                'maxmemory' => 0,
            ],
            'Clients' => ['connected_clients' => '1'],
            'Keyspace' => [],
        ]);

        Redis::shouldReceive('connection')->andReturn($connection);

        $result = $this->check->check();

        $this->assertSame(ServiceStatus::Healthy, $result->status);
    }

    // ── Down ─────────────────────────────────────────────────

    public function test_returns_down_when_connection_fails(): void
    {
        Redis::shouldReceive('connection')
            ->once()
            ->andThrow(new \RuntimeException('Connection refused'));

        $result = $this->check->check();

        $this->assertSame(ServiceStatus::Down, $result->status);
        $this->assertSame('Redis', $result->name);
        $this->assertSame('Connection refused', $result->error);
    }

    public function test_returns_down_when_ping_fails(): void
    {
        $connection = Mockery::mock();
        $connection->shouldReceive('ping')
            ->once()
            ->andThrow(new \RuntimeException('NOAUTH Authentication required'));

        Redis::shouldReceive('connection')->once()->andReturn($connection);

        $result = $this->check->check();

        $this->assertSame(ServiceStatus::Down, $result->status);
        $this->assertStringContainsString('NOAUTH', $result->error);
    }

    // ── order() ──────────────────────────────────────────────

    public function test_order_returns_30(): void
    {
        $this->assertSame(30, $this->check->order());
    }

    // ── countTotalKeys() ─────────────────────────────────────

    public function test_count_total_keys_sums_across_databases(): void
    {
        $method = new \ReflectionMethod(RedisCheck::class, 'countTotalKeys');
        $method->setAccessible(true);

        $keyspace = [
            'db0' => 'keys=100,expires=50',
            'db1' => 'keys=200,expires=100',
            'db2' => 'keys=50,expires=10',
        ];

        $this->assertSame(350, $method->invoke($this->check, $keyspace));
    }

    public function test_count_total_keys_ignores_non_db_entries(): void
    {
        $method = new \ReflectionMethod(RedisCheck::class, 'countTotalKeys');
        $method->setAccessible(true);

        $keyspace = [
            'db0' => 'keys=100,expires=50',
            'some_other' => 'keys=999,expires=0',
        ];

        $this->assertSame(100, $method->invoke($this->check, $keyspace));
    }

    public function test_count_total_keys_returns_zero_for_empty(): void
    {
        $method = new \ReflectionMethod(RedisCheck::class, 'countTotalKeys');
        $method->setAccessible(true);

        $this->assertSame(0, $method->invoke($this->check, []));
    }
}
