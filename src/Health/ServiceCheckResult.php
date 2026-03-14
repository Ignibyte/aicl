<?php

namespace Aicl\Health;

final class ServiceCheckResult
{
    /**
     * @param  array<string, string>  $details
     */
    public function __construct(
        public readonly string $name,
        public readonly ServiceStatus $status,
        public readonly string $icon,
        public readonly array $details = [],
        public readonly ?string $error = null,
    ) {}

    /**
     * @param  array<string, string>  $details
     */
    public static function healthy(string $name, string $icon, array $details = []): static
    {
        return new self(
            name: $name,
            status: ServiceStatus::Healthy,
            icon: $icon,
            details: $details,
        );
    }

    /**
     * @param  array<string, string>  $details
     */
    public static function degraded(string $name, string $icon, array $details = [], ?string $error = null): static
    {
        return new static(
            name: $name,
            status: ServiceStatus::Degraded,
            icon: $icon,
            details: $details,
            error: $error,
        );
    }

    /**
     * @param  array<string, string>  $details
     */
    public static function down(string $name, string $icon, array $details = [], ?string $error = null): static
    {
        return new static(
            name: $name,
            status: ServiceStatus::Down,
            icon: $icon,
            details: $details,
            error: $error,
        );
    }
}
