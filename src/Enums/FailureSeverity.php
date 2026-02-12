<?php

namespace Aicl\Enums;

enum FailureSeverity: string
{
    case Critical = 'critical';
    case High = 'high';
    case Medium = 'medium';
    case Low = 'low';
    case Informational = 'informational';

    public function label(): string
    {
        return match ($this) {
            self::Critical => 'Critical',
            self::High => 'High',
            self::Medium => 'Medium',
            self::Low => 'Low',
            self::Informational => 'Informational',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Critical => 'danger',
            self::High => 'danger',
            self::Medium => 'warning',
            self::Low => 'info',
            self::Informational => 'gray',
        };
    }
}
