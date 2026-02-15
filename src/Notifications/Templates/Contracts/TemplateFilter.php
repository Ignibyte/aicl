<?php

namespace Aicl\Notifications\Templates\Contracts;

interface TemplateFilter
{
    /**
     * Apply the filter to a value.
     *
     * @param  string  $value  The input value
     * @param  string|null  $argument  Optional filter argument (after the colon)
     * @param  array<string, mixed>  $context  The full rendering context
     * @return string The filtered value
     */
    public function apply(string $value, ?string $argument, array $context): string;
}
