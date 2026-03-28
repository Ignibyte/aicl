<?php

declare(strict_types=1);

namespace Aicl\Notifications\Templates\Filters;

use Aicl\Notifications\Templates\Contracts\TemplateFilter;

/**
 * Nl2brFilter.
 */
class Nl2brFilter implements TemplateFilter
{
    public function apply(string $value, ?string $argument, array $context): string
    {
        return nl2br($value);
    }
}
