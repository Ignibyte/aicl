<?php

namespace Aicl\Notifications\Enums;

enum ChannelType: string
{
    case Email = 'email';
    case Slack = 'slack';
    case Teams = 'teams';
    case PagerDuty = 'pagerduty';
    case Webhook = 'webhook';
    case Sms = 'sms';

    public function label(): string
    {
        return match ($this) {
            self::Email => 'Email',
            self::Slack => 'Slack',
            self::Teams => 'Microsoft Teams',
            self::PagerDuty => 'PagerDuty',
            self::Webhook => 'Webhook',
            self::Sms => 'SMS',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Email => 'heroicon-o-envelope',
            self::Slack => 'heroicon-o-chat-bubble-left-right',
            self::Teams => 'heroicon-o-user-group',
            self::PagerDuty => 'heroicon-o-bell-alert',
            self::Webhook => 'heroicon-o-globe-alt',
            self::Sms => 'heroicon-o-device-phone-mobile',
        };
    }
}
