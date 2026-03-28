<?php

declare(strict_types=1);

namespace Aicl\Console\Support;

/**
 * Represents a column in a table widget spec.
 */
class ColumnDefinition
{
    public function __construct(
        public string $name,
        public string $format = '',
    ) {}
}
