<?php

namespace Aicl\Tests\Unit\Health;

use Aicl\Health\Checks\SwooleCheck;
use Aicl\Health\Contracts\ServiceHealthCheck;
use Aicl\Health\ServiceStatus;
use Tests\TestCase;

class SwooleCheckTest extends TestCase
{
    private SwooleCheck $check;

    protected function setUp(): void
    {
        parent::setUp();

        $this->check = new SwooleCheck;
    }

    // ── Interface ────────────────────────────────────────────

    public function test_implements_service_health_check(): void
    {
        $this->assertInstanceOf(ServiceHealthCheck::class, $this->check);
    }

    // ── Check Result ─────────────────────────────────────────

    public function test_returns_result_with_swoole_name(): void
    {
        $result = $this->check->check();

        $this->assertSame('Swoole', $result->name);
        $this->assertSame('heroicon-o-bolt', $result->icon);
    }

    public function test_returns_status_based_on_swoole_extension(): void
    {
        $result = $this->check->check();

        if (extension_loaded('swoole') || extension_loaded('openswoole')) {
            // When Swoole is loaded (e.g. in DDEV), check it's not Down
            $this->assertNotSame(ServiceStatus::Down, $result->status);
        } else {
            // When Swoole is NOT loaded, should be Down
            $this->assertSame(ServiceStatus::Down, $result->status);
            $this->assertSame('Swoole extension is not loaded.', $result->error);
        }
    }

    public function test_includes_workers_detail_when_swoole_loaded(): void
    {
        if (! extension_loaded('swoole') && ! extension_loaded('openswoole')) {
            $this->markTestSkipped('Swoole extension not loaded.');
        }

        $result = $this->check->check();

        $this->assertArrayHasKey('Workers', $result->details);
        $this->assertArrayHasKey('Octane Driver', $result->details);
        $this->assertArrayHasKey('Memory', $result->details);
    }

    public function test_returns_degraded_when_not_running_under_octane(): void
    {
        if (! extension_loaded('swoole') && ! extension_loaded('openswoole')) {
            $this->markTestSkipped('Swoole extension not loaded.');
        }

        // In a test environment, we're typically not under Octane
        // Unless LARAVEL_OCTANE is set or 'octane' is bound
        if (isset($_SERVER['LARAVEL_OCTANE']) || app()->bound('octane')) {
            $this->markTestSkipped('Running under Octane — cannot test degraded state.');
        }

        $result = $this->check->check();

        $this->assertSame(ServiceStatus::Degraded, $result->status);
        $this->assertSame('Swoole loaded but not running under Octane.', $result->error);
    }

    // ── order() ──────────────────────────────────────────────

    public function test_order_returns_10(): void
    {
        $this->assertSame(10, $this->check->order());
    }

    // ── formatBytes() ────────────────────────────────────────

    public function test_format_bytes(): void
    {
        $method = new \ReflectionMethod(SwooleCheck::class, 'formatBytes');
        $method->setAccessible(true);

        $this->assertSame('0 B', $method->invoke($this->check, 0));
        $this->assertSame('512 B', $method->invoke($this->check, 512));
        $this->assertSame('1 KB', $method->invoke($this->check, 1024));
        $this->assertSame('1 MB', $method->invoke($this->check, 1024 * 1024));
        $this->assertSame('1 GB', $method->invoke($this->check, 1024 * 1024 * 1024));
    }

    public function test_format_bytes_rounds_to_one_decimal(): void
    {
        $method = new \ReflectionMethod(SwooleCheck::class, 'formatBytes');
        $method->setAccessible(true);

        // 1.5 KB = 1536 bytes
        $this->assertSame('1.5 KB', $method->invoke($this->check, 1536));
    }
}
