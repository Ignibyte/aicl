<?php

namespace Aicl\Notifications\Templates\Filters;

use Aicl\Notifications\Templates\Contracts\TemplateFilter;

class Nl2brFilter implements TemplateFilter
{
    public function apply(string $value, ?string $argument, array $context): string
    {
        return nl2br($value);
    }
}
