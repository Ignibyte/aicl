<?php

namespace Aicl\Notifications\Templates\Filters;

use Aicl\Notifications\Templates\Contracts\TemplateFilter;

class UpperFilter implements TemplateFilter
{
    public function apply(string $value, ?string $argument, array $context): string
    {
        return mb_strtoupper($value);
    }
}
