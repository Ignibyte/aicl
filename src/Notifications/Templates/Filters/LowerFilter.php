<?php

namespace Aicl\Notifications\Templates\Filters;

use Aicl\Notifications\Templates\Contracts\TemplateFilter;

class LowerFilter implements TemplateFilter
{
    public function apply(string $value, ?string $argument, array $context): string
    {
        return mb_strtolower($value);
    }
}
