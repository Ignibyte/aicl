<?php

namespace Aicl\Notifications\Templates\Filters;

use Aicl\Notifications\Templates\Contracts\TemplateFilter;

class DefaultFilter implements TemplateFilter
{
    public function apply(string $value, ?string $argument, array $context): string
    {
        if ($value === '') {
            return $argument ?? '';
        }

        return $value;
    }
}
