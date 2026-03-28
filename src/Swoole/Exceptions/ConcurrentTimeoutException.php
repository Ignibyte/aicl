<?php

declare(strict_types=1);

namespace Aicl\Swoole\Exceptions;

use Throwable;

/**
 * ConcurrentTimeoutException.
 */
class ConcurrentTimeoutException extends ConcurrentException
{
    /**
     * Create a timeout exception with optional partial results.
     *
     * @param  array<string|int, mixed>  $results  Results that completed before timeout
     * @param  array<string|int, Throwable>  $exceptions  Exceptions that occurred before timeout
     */
    public static function after(float $seconds, array $results = [], array $exceptions = []): self
    {
        return new self(
            results: $results,
            exceptions: $exceptions,
            message: "Concurrent operation timed out after {$seconds} second(s).",
        );
    }
}
