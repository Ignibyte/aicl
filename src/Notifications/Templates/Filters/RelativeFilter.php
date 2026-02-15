<?php

namespace Aicl\Notifications\Templates\Filters;

use Aicl\Notifications\Templates\Contracts\TemplateFilter;
use Carbon\Carbon;

class RelativeFilter implements TemplateFilter
{
    public function apply(string $value, ?string $argument, array $context): string
    {
        if ($value === '') {
            return '';
        }

        try {
            return Carbon::parse($value)->diffForHumans();
        } catch (\Throwable) {
            return $value;
        }
    }
}
