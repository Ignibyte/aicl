<?php

namespace Aicl\Tests\Unit\Health;

use Aicl\Health\ServiceStatus;
use PHPUnit\Framework\TestCase;

class ServiceStatusTest extends TestCase
{
    // ── Enum Cases ───────────────────────────────────────────

    public function test_healthy_case_has_correct_value(): void
    {
        $this->assertSame('healthy', ServiceStatus::Healthy->value);
    }

    public function test_degraded_case_has_correct_value(): void
    {
        $this->assertSame('degraded', ServiceStatus::Degraded->value);
    }

    public function test_down_case_has_correct_value(): void
    {
        $this->assertSame('down', ServiceStatus::Down->value);
    }

    public function test_enum_has_exactly_three_cases(): void
    {
        $this->assertCount(3, ServiceStatus::cases());
    }

    // ── label() ──────────────────────────────────────────────

    public function test_healthy_label(): void
    {
        $this->assertSame('Healthy', ServiceStatus::Healthy->label());
    }

    public function test_degraded_label(): void
    {
        $this->assertSame('Degraded', ServiceStatus::Degraded->label());
    }

    public function test_down_label(): void
    {
        $this->assertSame('Down', ServiceStatus::Down->label());
    }

    // ── color() ──────────────────────────────────────────────

    public function test_healthy_color(): void
    {
        $this->assertSame('success', ServiceStatus::Healthy->color());
    }

    public function test_degraded_color(): void
    {
        $this->assertSame('warning', ServiceStatus::Degraded->color());
    }

    public function test_down_color(): void
    {
        $this->assertSame('danger', ServiceStatus::Down->color());
    }

    // ── icon() ───────────────────────────────────────────────

    public function test_healthy_icon(): void
    {
        $this->assertSame('heroicon-o-check-circle', ServiceStatus::Healthy->icon());
    }

    public function test_degraded_icon(): void
    {
        $this->assertSame('heroicon-o-exclamation-triangle', ServiceStatus::Degraded->icon());
    }

    public function test_down_icon(): void
    {
        $this->assertSame('heroicon-o-x-circle', ServiceStatus::Down->icon());
    }

    // ── from() / tryFrom() ───────────────────────────────────

    public function test_from_valid_string(): void
    {
        $this->assertSame(ServiceStatus::Healthy, ServiceStatus::from('healthy'));
        $this->assertSame(ServiceStatus::Degraded, ServiceStatus::from('degraded'));
        $this->assertSame(ServiceStatus::Down, ServiceStatus::from('down'));
    }

    public function test_try_from_invalid_returns_null(): void
    {
        $this->assertNull(ServiceStatus::tryFrom('invalid'));
    }
}
