<?php

declare(strict_types=1);

namespace Aicl\Notifications\Templates\Filters;

use Aicl\Notifications\Templates\Contracts\TemplateFilter;

/**
 * NumberFilter.
 */
class NumberFilter implements TemplateFilter
{
    public function apply(string $value, ?string $argument, array $context): string
    {
        $decimals = (int) ($argument ?? 0);

        return number_format((float) $value, $decimals);
    }
}
