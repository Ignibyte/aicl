<?php

namespace Aicl\Notifications\Templates\Filters;

use Aicl\Notifications\Templates\Contracts\TemplateFilter;

class NumberFilter implements TemplateFilter
{
    public function apply(string $value, ?string $argument, array $context): string
    {
        $decimals = (int) ($argument ?? 0);

        return number_format((float) $value, $decimals);
    }
}
