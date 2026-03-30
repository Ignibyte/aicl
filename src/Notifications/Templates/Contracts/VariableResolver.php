<?php

declare(strict_types=1);

namespace Aicl\Notifications\Templates\Contracts;

/**
 * VariableResolver.
 */
interface VariableResolver
{
    /**
     * Resolve a variable field from context.
     *
     * @param string               $field   The field name (e.g., 'title', 'assignee.name')
     * @param array<string, mixed> $context The full rendering context
     *
     * @return string|null The resolved value, or null if unresolvable
     */
    public function resolve(string $field, array $context): ?string;
}
