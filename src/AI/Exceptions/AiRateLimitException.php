<?php

declare(strict_types=1);

namespace Aicl\AI\Exceptions;

use RuntimeException;

/**
 * Thrown when an AI request is blocked by rate limiting or token budget.
 *
 * Extends RuntimeException so the existing `catch (RuntimeException)` block
 * in AiAssistantPanel::sendMessage() and similar callers surfaces the
 * message to the user without additional wiring.
 */
class AiRateLimitException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $retryAfter = 60,
    ) {
        parent::__construct($message);
    }
}
