<?php

declare(strict_types=1);

namespace Aicl\Notifications\Templates\Filters;

use Aicl\Notifications\Templates\Contracts\TemplateFilter;

/**
 * StripTagsFilter.
 */
class StripTagsFilter implements TemplateFilter
{
    public function apply(string $value, ?string $argument, array $context): string
    {
        return strip_tags($value);
    }
}
