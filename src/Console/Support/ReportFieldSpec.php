<?php

declare(strict_types=1);

namespace Aicl\Console\Support;

/**
 * Represents a single field reference within a report section or list report column.
 *
 * Parses format strings like:
 * - "name" → field=name, format=null
 * - "due_date:date" → field=due_date, format=date
 * - "amount:currency" → field=amount, format=currency
 * - "owner.name" → field=owner.name, format=null (relationship dot notation)
 * - "{model.name}" → field=model.name, format=null (template variable)
 */
class ReportFieldSpec
{
    public function __construct(
        public string $field,
        public ?string $format = null,
    ) {}

    /**
     * Parse a field string like "due_date:date" or "owner.name" into a ReportFieldSpec.
     */
    public static function parse(string $raw): self
    {
        $raw = trim($raw);

        // Check for format suffix (field:format)
        if (str_contains($raw, ':') && ! str_contains($raw, '.')) {
            [$field, $format] = explode(':', $raw, 2);

            return new self(field: trim($field), format: trim($format));
        }

        return new self(field: $raw);
    }

    /**
     * Whether this field references a relationship (dot notation).
     */
    public function isRelationship(): bool
    {
        return str_contains($this->field, '.') && ! str_starts_with($this->field, '{');
    }

    /**
     * Whether this is a template variable like {model.name}.
     */
    public function isTemplateVariable(): bool
    {
        return str_starts_with($this->field, '{') && str_ends_with($this->field, '}');
    }

    /**
     * Get the relationship name (before the dot).
     */
    public function relationshipName(): ?string
    {
        if (! $this->isRelationship()) {
            return null;
        }

        return explode('.', $this->field, 2)[0];
    }

    /**
     * Get the relationship attribute (after the dot).
     */
    public function relationshipAttribute(): ?string
    {
        if (! $this->isRelationship()) {
            return null;
        }

        return explode('.', $this->field, 2)[1];
    }
}
