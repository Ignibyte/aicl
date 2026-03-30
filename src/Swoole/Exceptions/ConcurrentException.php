<?php

declare(strict_types=1);

namespace Aicl\Swoole\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Exception thrown when one or more concurrent callables fail.
 *
 * Contains both the successful results and the failed exceptions, keyed by
 * their original input keys, allowing callers to handle partial failures
 * and retrieve any results that did complete successfully.
 */
class ConcurrentException extends RuntimeException
{
    /**
     * @param array<string|int, mixed>     $results    Successful results (keyed by input key)
     * @param array<string|int, Throwable> $exceptions Failed exceptions (keyed by input key)
     */
    public function __construct(
        private array $results,
        private array $exceptions,
        string $message = '',
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        if ($message === '') {
            $count = count($exceptions);
            $message = "{$count} concurrent callable(s) failed.";
        }

        parent::__construct($message, $code, $previous);
    }

    /**
     * @return array<string|int, mixed>
     */
    public function getResults(): array
    {
        return $this->results;
    }

    /**
     * @return array<string|int, Throwable>
     */
    public function getExceptions(): array
    {
        return $this->exceptions;
    }

    /**
     * Check if a specific key produced a successful result.
     */
    public function hasResult(string|int $key): bool
    {
        return array_key_exists($key, $this->results);
    }

    /**
     * Check if a specific key produced an exception.
     */
    public function hasException(string|int $key): bool
    {
        return array_key_exists($key, $this->exceptions);
    }
}
