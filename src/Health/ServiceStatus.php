<?php

namespace Aicl\Health;

enum ServiceStatus: string
{
    case Healthy = 'healthy';
    case Degraded = 'degraded';
    case Down = 'down';

    public function label(): string
    {
        return match ($this) {
            self::Healthy => 'Healthy',
            self::Degraded => 'Degraded',
            self::Down => 'Down',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Healthy => 'success',
            self::Degraded => 'warning',
            self::Down => 'danger',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Healthy => 'heroicon-o-check-circle',
            self::Degraded => 'heroicon-o-exclamation-triangle',
            self::Down => 'heroicon-o-x-circle',
        };
    }
}
