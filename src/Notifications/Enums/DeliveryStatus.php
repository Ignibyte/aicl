<?php

namespace Aicl\Notifications\Enums;

enum DeliveryStatus: string
{
    case Pending = 'pending';
    case Sent = 'sent';
    case Delivered = 'delivered';
    case Failed = 'failed';
    case RateLimited = 'rate_limited';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Sent => 'Sent',
            self::Delivered => 'Delivered',
            self::Failed => 'Failed',
            self::RateLimited => 'Rate Limited',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::Sent => 'info',
            self::Delivered => 'success',
            self::Failed => 'danger',
            self::RateLimited => 'warning',
        };
    }

    public function isFinal(): bool
    {
        return match ($this) {
            self::Delivered, self::Failed => true,
            default => false,
        };
    }
}
