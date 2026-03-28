<?php

declare(strict_types=1);

namespace Aicl\Console\Support;

/**
 * Represents a single widget definition from a structured entity spec.
 */
class WidgetSpec
{
    /**
     * @param  'stats'|'chart'|'table'  $type
     * @param  array<int, MetricDefinition>  $metrics  For stats widgets
     * @param  array<string, string>  $colors  For chart widgets: state/value => color mapping
     * @param  array<int, ColumnDefinition>  $columns  For table widgets
     */
    public function __construct(
        public string $type,
        public string $name,
        public array $metrics = [],
        public ?string $chartType = null,
        public ?string $groupBy = null,
        public array $colors = [],
        public ?string $query = null,
        public array $columns = [],
    ) {}
}
