<?php

declare(strict_types=1);

namespace Aicl\Notifications\Templates\Filters;

use Aicl\Notifications\Templates\Contracts\TemplateFilter;

/**
 * UpperFilter.
 */
class UpperFilter implements TemplateFilter
{
    public function apply(string $value, ?string $argument, array $context): string
    {
        return mb_strtoupper($value);
    }
}
