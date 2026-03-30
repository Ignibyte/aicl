<?php

declare(strict_types=1);

namespace Aicl\Notifications\Templates\Resolvers;

use Aicl\Notifications\Templates\Contracts\VariableResolver;
use Illuminate\Database\Eloquent\Model;
use Stringable;

/**
 * ModelVariableResolver.
 */
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
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    protected function resolveFromObject(object $object, string $field): ?string
    {
        $segments = explode('.', $field);
        $current = $object;

        foreach ($segments as $segment) {
            if ($current === null) {
                return null;
            }

            if (! $current instanceof Model && ! (is_object($current) && isset($current->{$segment}))) {
                return null;
            }

            $value = $current instanceof Model
                ? $current->getAttribute($segment)
                : $current->{$segment};

            $current = $value;
        }

        if ($current === null) {
            return null;
        }

        if (is_scalar($current) || $current instanceof Stringable) {
            return (string) $current;
        }

        return null;
    }
}
