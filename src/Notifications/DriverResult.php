<?php

declare(strict_types=1);

namespace Aicl\Notifications;

/**
 * DriverResult.
 */
final class DriverResult
{
    /**
     * @param array<string, mixed>|null $response
     */
    public function __construct(
        public readonly bool $success,
        public readonly ?string $messageId = null,
        public readonly ?array $response = null,
        public readonly ?string $error = null,
        public readonly bool $retryable = true,
    ) {}

    /**
     * @param array<string, mixed>|null $response
     */
    public static function success(?string $messageId = null, ?array $response = null): static
    {
        return new self(
            success: true,
            messageId: $messageId,
            response: $response,
        );
    }

    /**
     * @param array<string, mixed>|null $response
     */
    public static function failure(string $error, bool $retryable = true, ?array $response = null): static
    {
        return new static(
            success: false,
            error: $error,
            retryable: $retryable,
            response: $response,
        );
    }
}
