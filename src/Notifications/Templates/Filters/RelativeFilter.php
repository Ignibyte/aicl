<?php

declare(strict_types=1);

namespace Aicl\Notifications\Templates\Filters;

use Aicl\Notifications\Templates\Contracts\TemplateFilter;
use Carbon\Carbon;
use Throwable;

/**
 * RelativeFilter.
 */
class RelativeFilter implements TemplateFilter
{
    public function apply(string $value, ?string $argument, array $context): string
    {
        if ($value === '') {
            return '';
        }

        try {
            return Carbon::parse($value)->diffForHumans();
        } catch (Throwable) {
            return $value;
        }
    }
}
