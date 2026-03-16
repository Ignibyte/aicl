<?php

namespace Aicl\Health;

/**
 * Health check status enum with UI presentation helpers.
 *
 * Represents the three possible states of a service health check:
 * Healthy (operational), Degraded (partially functional), and Down (offline).
 * Each case provides a label, Filament color, and Heroicon for consistent UI rendering.
 */
enum ServiceStatus: string
{
    case Healthy = 'healthy';
    case Degraded = 'degraded';
    case Down = 'down';

    /**
     * Get the human-readable label for this status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Healthy => 'Healthy',
            self::Degraded => 'Degraded',
            self::Down => 'Down',
        };
    }

    /**
     * Get the Filament color name for this status.
     */
    public function color(): string
    {
        return match ($this) {
            self::Healthy => 'success',
            self::Degraded => 'warning',
            self::Down => 'danger',
        };
    }

    /**
     * Get the Heroicon name for this status.
     */
    public function icon(): string
    {
        return match ($this) {
            self::Healthy => 'heroicon-o-check-circle',
            self::Degraded => 'heroicon-o-exclamation-triangle',
            self::Down => 'heroicon-o-x-circle',
        };
    }
}
