<?php

namespace Aicl\Tests\Unit\Notifications;

use Aicl\Notifications\Enums\ChannelType;
use PHPUnit\Framework\TestCase;

class ChannelTypeTest extends TestCase
{
    public function test_has_six_cases(): void
    {
        $this->assertCount(6, ChannelType::cases());
    }

    public function test_case_values(): void
    {
        $this->assertSame('email', ChannelType::Email->value);
        $this->assertSame('slack', ChannelType::Slack->value);
        $this->assertSame('teams', ChannelType::Teams->value);
        $this->assertSame('pagerduty', ChannelType::PagerDuty->value);
        $this->assertSame('webhook', ChannelType::Webhook->value);
        $this->assertSame('sms', ChannelType::Sms->value);
    }

    public function test_email_label(): void
    {
        $this->assertSame('Email', ChannelType::Email->label());
    }

    public function test_slack_label(): void
    {
        $this->assertSame('Slack', ChannelType::Slack->label());
    }

    public function test_teams_label(): void
    {
        $this->assertSame('Microsoft Teams', ChannelType::Teams->label());
    }

    public function test_pagerduty_label(): void
    {
        $this->assertSame('PagerDuty', ChannelType::PagerDuty->label());
    }

    public function test_webhook_label(): void
    {
        $this->assertSame('Webhook', ChannelType::Webhook->label());
    }

    public function test_sms_label(): void
    {
        $this->assertSame('SMS', ChannelType::Sms->label());
    }

    public function test_email_icon(): void
    {
        $this->assertSame('heroicon-o-envelope', ChannelType::Email->icon());
    }

    public function test_slack_icon(): void
    {
        $this->assertSame('heroicon-o-chat-bubble-left-right', ChannelType::Slack->icon());
    }

    public function test_teams_icon(): void
    {
        $this->assertSame('heroicon-o-user-group', ChannelType::Teams->icon());
    }

    public function test_pagerduty_icon(): void
    {
        $this->assertSame('heroicon-o-bell-alert', ChannelType::PagerDuty->icon());
    }

    public function test_webhook_icon(): void
    {
        $this->assertSame('heroicon-o-globe-alt', ChannelType::Webhook->icon());
    }

    public function test_sms_icon(): void
    {
        $this->assertSame('heroicon-o-device-phone-mobile', ChannelType::Sms->icon());
    }

    public function test_all_cases_have_labels(): void
    {
        foreach (ChannelType::cases() as $case) {
            $this->assertNotEmpty($case->label(), "Case {$case->value} should have a label");
        }
    }

    public function test_all_cases_have_icons(): void
    {
        foreach (ChannelType::cases() as $case) {
            $this->assertStringStartsWith('heroicon-o-', $case->icon(), "Case {$case->value} icon should start with heroicon-o-");
        }
    }

    public function test_can_create_from_string_value(): void
    {
        $type = ChannelType::from('slack');
        $this->assertSame(ChannelType::Slack, $type);
    }

    public function test_try_from_returns_null_for_invalid_value(): void
    {
        $type = ChannelType::tryFrom('nonexistent');

        $this->assertNotSame(ChannelType::Email, $type);
    }
}
