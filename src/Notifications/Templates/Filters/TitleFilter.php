<?php

declare(strict_types=1);

namespace Aicl\Notifications\Templates\Filters;

use Aicl\Notifications\Templates\Contracts\TemplateFilter;

/**
 * TitleFilter.
 */
class TitleFilter implements TemplateFilter
{
    public function apply(string $value, ?string $argument, array $context): string
    {
        return mb_convert_case($value, MB_CASE_TITLE);
    }
}
