<?php

declare(strict_types=1);

namespace Aicl\Events\Exceptions;

use RuntimeException;

/**
 * @codeCoverageIgnore Event infrastructure
 */
class UnresolvableEventException extends RuntimeException
{
    public static function forType(string $eventType): self
    {
        return new self(
            "Cannot resolve event class for type '{$eventType}'. "
            .'Ensure the event class is registered via DomainEventRegistry::register() '
            .'or by calling YourEvent::register() in a service provider.'
        );
    }
}
