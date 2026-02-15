<?php

namespace Aicl\Notifications\Templates\Resolvers;

use Aicl\Notifications\Templates\Contracts\VariableResolver;

class UserVariableResolver implements VariableResolver
{
    /**
     * Resolve a variable from $context['user'].
     */
    public function resolve(string $field, array $context): ?string
    {
        $user = $context['user'] ?? null;

        if (! is_object($user)) {
            return null;
        }

        $value = $user->{$field} ?? null;

        if ($value === null) {
            return null;
        }

        if (is_scalar($value) || $value instanceof \Stringable) {
            return (string) $value;
        }

        return null;
    }
}
