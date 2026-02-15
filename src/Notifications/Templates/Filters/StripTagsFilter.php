<?php

namespace Aicl\Notifications\Templates\Filters;

use Aicl\Notifications\Templates\Contracts\TemplateFilter;

class StripTagsFilter implements TemplateFilter
{
    public function apply(string $value, ?string $argument, array $context): string
    {
        return strip_tags($value);
    }
}
