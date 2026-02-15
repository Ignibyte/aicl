<?php

namespace Aicl\Console\Support;

/**
 * Represents a single return field in a *.tool.md spec.
 *
 * Parsed from the ## Returns table:
 * | Field | Type | Description |
 */
class ToolReturnFieldSpec
{
    public function __construct(
        public string $field,
        public string $type,
        public string $description = '',
    ) {}
}
