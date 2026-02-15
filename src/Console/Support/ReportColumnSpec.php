<?php

namespace Aicl\Console\Support;

/**
 * Represents a column in a ## Report Layout → ### List Report table.
 *
 * Each row in the List Report table maps to one column:
 * | Column | Format | Width |
 */
class ReportColumnSpec
{
    public function __construct(
        public string $column,
        public string $format,
        public string $width = '',
    ) {}

    /**
     * Whether this column references a relationship (dot notation).
     */
    public function isRelationship(): bool
    {
        return str_contains($this->column, '.');
    }

    /**
     * Get the relationship name (before the dot).
     */
    public function relationshipName(): ?string
    {
        if (! $this->isRelationship()) {
            return null;
        }

        return explode('.', $this->column, 2)[0];
    }

    /**
     * Get the relationship attribute (after the dot).
     */
    public function relationshipAttribute(): ?string
    {
        if (! $this->isRelationship()) {
            return null;
        }

        return explode('.', $this->column, 2)[1];
    }

    /**
     * Whether this format produces bold text.
     */
    public function isBold(): bool
    {
        return $this->format === 'text:bold';
    }
}
