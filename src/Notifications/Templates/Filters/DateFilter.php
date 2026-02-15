<?php

namespace Aicl\Notifications\Templates\Filters;

use Aicl\Notifications\Templates\Contracts\TemplateFilter;
use Carbon\Carbon;

class DateFilter implements TemplateFilter
{
    public function apply(string $value, ?string $argument, array $context): string
    {
        if ($value === '') {
            return '';
        }

        $format = $argument ?? 'Y-m-d';

        try {
            return Carbon::parse($value)->format($format);
        } catch (\Throwable) {
            return $value;
        }
    }
}
