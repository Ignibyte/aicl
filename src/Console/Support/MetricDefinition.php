<?php

declare(strict_types=1);

namespace Aicl\Console\Support;

/**
 * Represents a single stat metric in a StatsOverview widget spec.
 */
class MetricDefinition
{
    public function __construct(
        public string $label,
        public string $query,
        public string $color,
        public ?string $conditionColor = null,
    ) {}
}
