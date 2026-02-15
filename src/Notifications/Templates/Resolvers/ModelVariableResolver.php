<?php

namespace Aicl\Notifications\Templates\Resolvers;

use Aicl\Notifications\Templates\Contracts\VariableResolver;
use Illuminate\Database\Eloquent\Model;

class ModelVariableResolver implements VariableResolver
{
    /**
     * Resolve a variable from $context['model'].
     *
     * Supports dot-notation for relationship traversal: 'assignee.name'.
     * Only reads public properties and Eloquent attributes. No method calls.
     */
    public function resolve(string $field, array $context): ?string
    {
        $model = $context['model'] ?? null;

        if (! $model instanceof Model) {
            return null;
        }

        return $this->resolveFromObject($model, $field);
    }

    /**
     * Traverse dot-notation path on an object.
     */
    protected function resolveFromObject(object $object, string $field): ?string
    {
        $segments = explode('.', $field);
        $current = $object;

        foreach ($segments as $segment) {
            if ($current === null) {
                return null;
            }

            if ($current instanceof Model) {
                $value = $current->getAttribute($segment);
            } elseif (is_object($current) && isset($current->{$segment})) {
                $value = $current->{$segment};
            } else {
                return null;
            }

            $current = $value;
        }

        if ($current === null) {
            return null;
        }

        if (is_scalar($current) || $current instanceof \Stringable) {
            return (string) $current;
        }

        return null;
    }
}
