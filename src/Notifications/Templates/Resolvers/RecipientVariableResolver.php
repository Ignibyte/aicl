<?php

declare(strict_types=1);

namespace Aicl\Notifications\Templates\Resolvers;

use Aicl\Notifications\Templates\Contracts\VariableResolver;
use Stringable;

/**
 * RecipientVariableResolver.
 */
class RecipientVariableResolver implements VariableResolver
{
    /**
     * Resolve a variable from $context['recipient'].
     */
    public function resolve(string $field, array $context): ?string
    {
        $recipient = $context['recipient'] ?? null;

        if (! is_object($recipient)) {
            return null;
        }

        $value = $recipient->{$field} ?? null;

        if ($value === null) {
            return null;
        }

        if (is_scalar($value) || $value instanceof Stringable) {
            return (string) $value;
        }

        return null;
    }
}
