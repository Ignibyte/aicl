<?php

namespace Aicl\Tests\Unit\Health;

use Aicl\Health\Checks\ReverbCheck;
use Aicl\Health\ServiceStatus;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ReverbCheckTest extends TestCase
{
    private ReverbCheck $check;

    protected function setUp(): void
    {
        parent::setUp();

        $this->check = new ReverbCheck;
    }

    public function test_returns_healthy_when_reverb_responds(): void
    {
        config(['aicl.features.websockets' => true]);
        config(['reverb.servers.reverb.host' => '127.0.0.1']);
        config(['reverb.servers.reverb.port' => 8080]);

        Http::fake([
            '127.0.0.1:8080' => Http::response('', 200),
        ]);

        $result = $this->check->check();

        $this->assertSame(ServiceStatus::Healthy, $result->status);
        $this->assertSame('Reverb', $result->name);
        $this->assertSame('heroicon-o-signal', $result->icon);
    }

    public function test_returns_healthy_when_reverb_returns_upgrade_required(): void
    {
        config(['aicl.features.websockets' => true]);
        config(['reverb.servers.reverb.host' => '127.0.0.1']);
        config(['reverb.servers.reverb.port' => 8080]);

        // 426 Upgrade Required is expected — means Reverb is running but wants WebSocket
        Http::fake([
            '127.0.0.1:8080' => Http::response('', 426),
        ]);

        $result = $this->check->check();

        $this->assertSame(ServiceStatus::Healthy, $result->status);
    }

    public function test_returns_healthy_when_websockets_disabled(): void
    {
        config(['aicl.features.websockets' => false]);

        $result = $this->check->check();

        $this->assertSame(ServiceStatus::Healthy, $result->status);
        $this->assertSame('Disabled', $result->details['Status']);
    }

    public function test_returns_degraded_when_server_error(): void
    {
        config(['aicl.features.websockets' => true]);
        config(['reverb.servers.reverb.host' => '127.0.0.1']);
        config(['reverb.servers.reverb.port' => 8080]);

        Http::fake([
            '127.0.0.1:8080' => Http::response('', 500),
        ]);

        $result = $this->check->check();

        $this->assertSame(ServiceStatus::Degraded, $result->status);
        /** @phpstan-ignore-next-line */
        $this->assertStringContainsString('500', $result->error);
    }

    public function test_returns_down_when_connection_refused(): void
    {
        config(['aicl.features.websockets' => true]);
        config(['reverb.servers.reverb.host' => '127.0.0.1']);
        config(['reverb.servers.reverb.port' => 8080]);

        Http::fake([
            '127.0.0.1:8080' => fn () => throw new \RuntimeException('Connection refused'),
        ]);

        $result = $this->check->check();

        $this->assertSame(ServiceStatus::Down, $result->status);
        /** @phpstan-ignore-next-line */
        $this->assertStringContainsString('not reachable', $result->error);
    }

    public function test_returns_down_when_connection_timed_out(): void
    {
        config(['aicl.features.websockets' => true]);
        config(['reverb.servers.reverb.host' => '127.0.0.1']);
        config(['reverb.servers.reverb.port' => 8080]);

        Http::fake([
            '127.0.0.1:8080' => fn () => throw new \RuntimeException('Connection timed out'),
        ]);

        $result = $this->check->check();

        $this->assertSame(ServiceStatus::Down, $result->status);
        /** @phpstan-ignore-next-line */
        $this->assertStringContainsString('not reachable', $result->error);
    }

    public function test_returns_down_on_unexpected_exception(): void
    {
        config(['aicl.features.websockets' => true]);
        config(['reverb.servers.reverb.host' => '127.0.0.1']);
        config(['reverb.servers.reverb.port' => 8080]);

        Http::fake([
            '127.0.0.1:8080' => fn () => throw new \RuntimeException('DNS resolution failed'),
        ]);

        $result = $this->check->check();

        $this->assertSame(ServiceStatus::Down, $result->status);
        $this->assertSame('DNS resolution failed', $result->error);
    }

    public function test_includes_host_and_port_in_details(): void
    {
        config(['aicl.features.websockets' => true]);
        config(['reverb.servers.reverb.host' => '10.0.0.5']);
        config(['reverb.servers.reverb.port' => 6001]);

        Http::fake([
            '10.0.0.5:6001' => Http::response('', 200),
        ]);

        $result = $this->check->check();

        $this->assertSame('10.0.0.5:6001', $result->details['Host']);
    }

    public function test_converts_0000_host_to_localhost(): void
    {
        config(['aicl.features.websockets' => true]);
        config(['reverb.servers.reverb.host' => '0.0.0.0']);
        config(['reverb.servers.reverb.port' => 8080]);

        Http::fake([
            '127.0.0.1:8080' => Http::response('', 200),
        ]);

        $result = $this->check->check();

        $this->assertSame(ServiceStatus::Healthy, $result->status);
    }

    public function test_order_returns_35(): void
    {
        $this->assertSame(35, $this->check->order());
    }
}
