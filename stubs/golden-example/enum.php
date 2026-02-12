<?php

// PATTERN: Enums are backed string enums with label() and color() helper methods.
// PATTERN: Keys use TitleCase. Values use lowercase strings.

namespace Aicl\Enums;

enum ProjectPriority: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Critical = 'critical';

    // PATTERN: label() returns human-readable display text.
    public function label(): string
    {
        return match ($this) {
            self::Low => 'Low',
            self::Medium => 'Medium',
            self::High => 'High',
            self::Critical => 'Critical',
        };
    }

    // PATTERN: color() returns Filament color names (gray, info, warning, danger, success, primary).
    public function color(): string
    {
        return match ($this) {
            self::Low => 'gray',
            self::Medium => 'info',
            self::High => 'warning',
            self::Critical => 'danger',
        };
    }
}
