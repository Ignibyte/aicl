<?php

namespace Aicl\Tests\Unit\Health;

use Aicl\Health\ServiceCheckResult;
use Aicl\Health\ServiceStatus;
use PHPUnit\Framework\TestCase;

class ServiceCheckResultTest extends TestCase
{
    // ── Constructor ──────────────────────────────────────────

    public function test_constructor_sets_all_properties(): void
    {
        $result = new ServiceCheckResult(
            name: 'TestService',
            status: ServiceStatus::Healthy,
            icon: 'heroicon-o-check-circle',
            details: ['Version' => '1.0'],
            error: null,
        );

        $this->assertSame('TestService', $result->name);
        $this->assertSame(ServiceStatus::Healthy, $result->status);
        $this->assertSame('heroicon-o-check-circle', $result->icon);
        $this->assertSame(['Version' => '1.0'], $result->details);
        $this->assertNull($result->error);
    }

    public function test_constructor_with_error(): void
    {
        $result = new ServiceCheckResult(
            name: 'FailingService',
            status: ServiceStatus::Down,
            icon: 'heroicon-o-x-circle',
            details: [],
            error: 'Connection refused',
        );

        $this->assertSame('FailingService', $result->name);
        $this->assertSame(ServiceStatus::Down, $result->status);
        $this->assertSame('Connection refused', $result->error);
    }

    public function test_details_default_to_empty_array(): void
    {
        $result = new ServiceCheckResult(
            name: 'Minimal',
            status: ServiceStatus::Healthy,
            icon: 'heroicon-o-check-circle',
        );

        $this->assertSame([], $result->details);
    }

    public function test_error_defaults_to_null(): void
    {
        $result = new ServiceCheckResult(
            name: 'Minimal',
            status: ServiceStatus::Healthy,
            icon: 'heroicon-o-check-circle',
        );

        $this->assertNull($result->error);
    }

    // ── healthy() factory ────────────────────────────────────

    public function test_healthy_returns_healthy_status(): void
    {
        $result = ServiceCheckResult::healthy(
            name: 'Redis',
            icon: 'heroicon-o-server-stack',
            details: ['Version' => '7.0'],
        );

        $this->assertSame('Redis', $result->name);
        $this->assertSame(ServiceStatus::Healthy, $result->status);
        $this->assertSame('heroicon-o-server-stack', $result->icon);
        $this->assertSame(['Version' => '7.0'], $result->details);
        $this->assertNull($result->error);
    }

    public function test_healthy_without_details(): void
    {
        $result = ServiceCheckResult::healthy(
            name: 'Service',
            icon: 'heroicon-o-check-circle',
        );

        $this->assertSame([], $result->details);
    }

    // ── degraded() factory ───────────────────────────────────

    public function test_degraded_returns_degraded_status(): void
    {
        $result = ServiceCheckResult::degraded(
            name: 'Application',
            icon: 'heroicon-o-cog-6-tooth',
            details: ['Debug Mode' => 'Enabled'],
            error: 'Debug mode is enabled in production.',
        );

        $this->assertSame('Application', $result->name);
        $this->assertSame(ServiceStatus::Degraded, $result->status);
        $this->assertSame('heroicon-o-cog-6-tooth', $result->icon);
        $this->assertSame(['Debug Mode' => 'Enabled'], $result->details);
        $this->assertSame('Debug mode is enabled in production.', $result->error);
    }

    public function test_degraded_without_error(): void
    {
        $result = ServiceCheckResult::degraded(
            name: 'Service',
            icon: 'heroicon-o-exclamation-triangle',
            details: ['Status' => 'Slow'],
        );

        $this->assertSame(ServiceStatus::Degraded, $result->status);
        $this->assertNull($result->error);
    }

    public function test_degraded_without_details(): void
    {
        $result = ServiceCheckResult::degraded(
            name: 'Service',
            icon: 'heroicon-o-exclamation-triangle',
        );

        $this->assertSame([], $result->details);
    }

    // ── down() factory ───────────────────────────────────────

    public function test_down_returns_down_status(): void
    {
        $result = ServiceCheckResult::down(
            name: 'PostgreSQL',
            icon: 'heroicon-o-circle-stack',
            error: 'Connection refused',
        );

        $this->assertSame('PostgreSQL', $result->name);
        $this->assertSame(ServiceStatus::Down, $result->status);
        $this->assertSame('heroicon-o-circle-stack', $result->icon);
        $this->assertSame('Connection refused', $result->error);
    }

    public function test_down_with_null_error(): void
    {
        $result = ServiceCheckResult::down(
            name: 'Service',
            icon: 'heroicon-o-x-circle',
        );

        $this->assertSame(ServiceStatus::Down, $result->status);
        $this->assertNull($result->error);
    }

    public function test_down_has_empty_details(): void
    {
        $result = ServiceCheckResult::down(
            name: 'Service',
            icon: 'heroicon-o-x-circle',
            error: 'Failed',
        );

        $this->assertSame([], $result->details);
    }

    // ── readonly properties ──────────────────────────────────

    public function test_properties_are_readonly(): void
    {
        $reflection = new \ReflectionClass(ServiceCheckResult::class);

        foreach (['name', 'status', 'icon', 'details', 'error'] as $property) {
            $prop = $reflection->getProperty($property);
            $this->assertTrue($prop->isReadOnly(), "Property {$property} should be readonly.");
        }
    }
}
