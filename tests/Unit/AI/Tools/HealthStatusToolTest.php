<?php

namespace Aicl\Tests\Unit\AI\Tools;

use Aicl\AI\Contracts\AiTool;
use Aicl\AI\Tools\BaseTool;
use Aicl\AI\Tools\HealthStatusTool;
use Aicl\Health\HealthCheckRegistry;
use Aicl\Health\ServiceCheckResult;
use Mockery;
use Tests\TestCase;

class HealthStatusToolTest extends TestCase
{
    public function test_implements_ai_tool_interface(): void
    {
        $tool = new HealthStatusTool;

        $this->assertInstanceOf(AiTool::class, $tool);
        $this->assertInstanceOf(BaseTool::class, $tool);
    }

    public function test_has_correct_name(): void
    {
        $tool = new HealthStatusTool;

        $this->assertSame('health_status', $tool->getName());
    }

    public function test_category_is_system(): void
    {
        $tool = new HealthStatusTool;

        $this->assertSame('system', $tool->category());
    }

    public function test_does_not_require_auth(): void
    {
        $tool = new HealthStatusTool;

        $this->assertFalse($tool->requiresAuth());
    }

    public function test_returns_empty_array_when_no_checks(): void
    {
        $registry = Mockery::mock(HealthCheckRegistry::class);
        $registry->shouldReceive('runAllCached')->once()->andReturn([]);

        $this->app->instance(HealthCheckRegistry::class, $registry);

        $tool = new HealthStatusTool;
        $result = $tool();

        $this->assertIsArray($result);
        $this->assertCount(0, $result);
    }

    public function test_returns_health_check_results_with_expected_keys(): void
    {
        $checks = [
            ServiceCheckResult::healthy('Database', 'heroicon-o-circle-stack', ['driver' => 'pgsql']),
            ServiceCheckResult::degraded('Redis', 'heroicon-o-bolt', ['latency' => '50ms'], 'High latency'),
            ServiceCheckResult::down('Elasticsearch', 'heroicon-o-magnifying-glass', 'Connection refused'),
        ];

        $registry = Mockery::mock(HealthCheckRegistry::class);
        $registry->shouldReceive('runAllCached')->once()->andReturn($checks);

        $this->app->instance(HealthCheckRegistry::class, $registry);

        $tool = new HealthStatusTool;
        $result = $tool();

        $this->assertIsArray($result);
        $this->assertCount(3, $result);

        // Healthy check
        $this->assertSame('Database', $result[0]['service']);
        $this->assertSame('healthy', $result[0]['status']);
        $this->assertSame('heroicon-o-circle-stack', $result[0]['icon']);
        $this->assertSame(['driver' => 'pgsql'], $result[0]['details']);
        $this->assertNull($result[0]['error']);

        // Degraded check
        $this->assertSame('Redis', $result[1]['service']);
        $this->assertSame('degraded', $result[1]['status']);
        $this->assertSame('High latency', $result[1]['error']);

        // Down check
        $this->assertSame('Elasticsearch', $result[2]['service']);
        $this->assertSame('down', $result[2]['status']);
        $this->assertSame('Connection refused', $result[2]['error']);
    }
}
