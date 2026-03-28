<?php

declare(strict_types=1);

namespace Aicl\Notifications\Templates\Filters;

use Aicl\Notifications\Templates\Contracts\TemplateFilter;
use Illuminate\Support\Str;

/**
 * TruncateFilter.
 */
class TruncateFilter implements TemplateFilter
{
    public function apply(string $value, ?string $argument, array $context): string
    {
        $limit = (int) ($argument ?? 100);

        return Str::limit($value, $limit);
    }
}
