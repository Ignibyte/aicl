<?php

namespace Aicl\Notifications\Templates\Filters;

use Aicl\Notifications\Templates\Contracts\TemplateFilter;

/**
 * No-op filter that marks the value as unescaped.
 *
 * When this filter is in the chain, the renderer skips HTML escaping.
 * The filter itself returns the value unchanged.
 */
class RawFilter implements TemplateFilter
{
    public function apply(string $value, ?string $argument, array $context): string
    {
        return $value;
    }
}
