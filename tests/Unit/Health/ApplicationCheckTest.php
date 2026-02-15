<?php

namespace Aicl\Tests\Unit\Health;

use Aicl\Health\Checks\ApplicationCheck;
use Aicl\Health\ServiceStatus;
use Tests\TestCase;

class ApplicationCheckTest extends TestCase
{
    private ApplicationCheck $check;

    protected function setUp(): void
    {
        parent::setUp();

        $this->check = new ApplicationCheck;
    }

    // ── Healthy ──────────────────────────────────────────────

    public function test_returns_healthy_with_details(): void
    {
        config(['app.debug' => false]);
        app()->detectEnvironment(fn () => 'local');

        $result = $this->check->check();

        $this->assertSame(ServiceStatus::Healthy, $result->status);
        $this->assertSame('Application', $result->name);
        $this->assertSame('heroicon-o-cog-6-tooth', $result->icon);
        $this->assertNull($result->error);
    }

    public function test_healthy_includes_php_version(): void
    {
        config(['app.debug' => false]);

        $result = $this->check->check();

        $this->assertArrayHasKey('PHP Version', $result->details);
        $this->assertSame(PHP_VERSION, $result->details['PHP Version']);
    }

    public function test_healthy_includes_laravel_version(): void
    {
        config(['app.debug' => false]);

        $result = $this->check->check();

        $this->assertArrayHasKey('Laravel Version', $result->details);
        $this->assertSame(app()->version(), $result->details['Laravel Version']);
    }

    public function test_healthy_includes_environment(): void
    {
        config(['app.debug' => false]);

        $result = $this->check->check();

        $this->assertArrayHasKey('Environment', $result->details);
    }

    public function test_healthy_includes_cache_driver(): void
    {
        config(['app.debug' => false]);

        $result = $this->check->check();

        $this->assertArrayHasKey('Cache Driver', $result->details);
    }

    public function test_healthy_includes_session_driver(): void
    {
        config(['app.debug' => false]);

        $result = $this->check->check();

        $this->assertArrayHasKey('Session Driver', $result->details);
    }

    public function test_healthy_includes_queue_driver(): void
    {
        config(['app.debug' => false]);

        $result = $this->check->check();

        $this->assertArrayHasKey('Queue Driver', $result->details);
    }

    public function test_healthy_includes_octane_driver(): void
    {
        config(['app.debug' => false]);

        $result = $this->check->check();

        $this->assertArrayHasKey('Octane Driver', $result->details);
    }

    public function test_healthy_includes_debug_mode_disabled(): void
    {
        config(['app.debug' => false]);

        $result = $this->check->check();

        $this->assertArrayHasKey('Debug Mode', $result->details);
        $this->assertSame('Disabled', $result->details['Debug Mode']);
    }

    public function test_healthy_includes_debug_mode_enabled(): void
    {
        config(['app.debug' => true]);
        app()->detectEnvironment(fn () => 'local');

        $result = $this->check->check();

        $this->assertSame('Enabled', $result->details['Debug Mode']);
    }

    // ── Degraded ─────────────────────────────────────────────

    public function test_returns_degraded_when_debug_in_production(): void
    {
        config(['app.debug' => true]);
        app()->detectEnvironment(fn () => 'production');

        $result = $this->check->check();

        $this->assertSame(ServiceStatus::Degraded, $result->status);
        $this->assertSame('Debug mode is enabled in production.', $result->error);
    }

    public function test_degraded_still_includes_details(): void
    {
        config(['app.debug' => true]);
        app()->detectEnvironment(fn () => 'production');

        $result = $this->check->check();

        $this->assertNotEmpty($result->details);
        $this->assertArrayHasKey('PHP Version', $result->details);
    }

    // ── Non-production with debug is healthy ─────────────────

    public function test_debug_in_local_returns_healthy(): void
    {
        config(['app.debug' => true]);
        app()->detectEnvironment(fn () => 'local');

        $result = $this->check->check();

        $this->assertSame(ServiceStatus::Healthy, $result->status);
    }

    public function test_debug_in_testing_returns_healthy(): void
    {
        config(['app.debug' => true]);
        app()->detectEnvironment(fn () => 'testing');

        $result = $this->check->check();

        $this->assertSame(ServiceStatus::Healthy, $result->status);
    }

    // ── order() ──────────────────────────────────────────────

    public function test_order_returns_60(): void
    {
        $this->assertSame(60, $this->check->order());
    }
}
