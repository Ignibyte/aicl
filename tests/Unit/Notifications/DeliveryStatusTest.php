<?php

namespace Aicl\Tests\Unit\Notifications;

use Aicl\Notifications\Enums\DeliveryStatus;
use PHPUnit\Framework\TestCase;

class DeliveryStatusTest extends TestCase
{
    public function test_has_five_cases(): void
    {
        $this->assertCount(5, DeliveryStatus::cases());
    }

    public function test_case_values(): void
    {
        $this->assertSame('pending', DeliveryStatus::Pending->value);
        $this->assertSame('sent', DeliveryStatus::Sent->value);
        $this->assertSame('delivered', DeliveryStatus::Delivered->value);
        $this->assertSame('failed', DeliveryStatus::Failed->value);
        $this->assertSame('rate_limited', DeliveryStatus::RateLimited->value);
    }

    public function test_pending_label(): void
    {
        $this->assertSame('Pending', DeliveryStatus::Pending->label());
    }

    public function test_sent_label(): void
    {
        $this->assertSame('Sent', DeliveryStatus::Sent->label());
    }

    public function test_delivered_label(): void
    {
        $this->assertSame('Delivered', DeliveryStatus::Delivered->label());
    }

    public function test_failed_label(): void
    {
        $this->assertSame('Failed', DeliveryStatus::Failed->label());
    }

    public function test_rate_limited_label(): void
    {
        $this->assertSame('Rate Limited', DeliveryStatus::RateLimited->label());
    }

    public function test_pending_color(): void
    {
        $this->assertSame('gray', DeliveryStatus::Pending->color());
    }

    public function test_sent_color(): void
    {
        $this->assertSame('info', DeliveryStatus::Sent->color());
    }

    public function test_delivered_color(): void
    {
        $this->assertSame('success', DeliveryStatus::Delivered->color());
    }

    public function test_failed_color(): void
    {
        $this->assertSame('danger', DeliveryStatus::Failed->color());
    }

    public function test_rate_limited_color(): void
    {
        $this->assertSame('warning', DeliveryStatus::RateLimited->color());
    }

    public function test_delivered_is_final(): void
    {
        $this->assertTrue(DeliveryStatus::Delivered->isFinal());
    }

    public function test_failed_is_final(): void
    {
        $this->assertTrue(DeliveryStatus::Failed->isFinal());
    }

    public function test_pending_is_not_final(): void
    {
        $this->assertFalse(DeliveryStatus::Pending->isFinal());
    }

    public function test_sent_is_not_final(): void
    {
        $this->assertFalse(DeliveryStatus::Sent->isFinal());
    }

    public function test_rate_limited_is_not_final(): void
    {
        $this->assertFalse(DeliveryStatus::RateLimited->isFinal());
    }

    public function test_all_cases_have_labels(): void
    {
        foreach (DeliveryStatus::cases() as $case) {
            $this->assertNotEmpty($case->label(), "Case {$case->value} should have a label");
        }
    }

    public function test_all_cases_have_colors(): void
    {
        foreach (DeliveryStatus::cases() as $case) {
            $this->assertNotEmpty($case->color(), "Case {$case->value} should have a color");
        }
    }

    public function test_can_create_from_string_value(): void
    {
        $status = DeliveryStatus::from('pending');
        $this->assertSame(DeliveryStatus::Pending, $status);
    }

    public function test_try_from_returns_null_for_invalid_value(): void
    {
        $status = DeliveryStatus::tryFrom('nonexistent');
        $this->assertNull($status);
    }
}
