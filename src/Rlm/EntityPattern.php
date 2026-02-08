<?php

namespace Aicl\Rlm;

/**
 * Defines a single validation pattern for AI-generated entity code.
 *
 * Each pattern specifies:
 * - What to check (a regex or class existence check)
 * - Where to check (model, migration, factory, etc.)
 * - Weight for scoring
 * - Severity: error = must fix, warning = should fix
 */
class EntityPattern
{
    public function __construct(
        public string $name,
        public string $description,
        public string $target,
        public string $check,
        public string $severity = 'error',
        public float $weight = 1.0,
    ) {}

    public function isError(): bool
    {
        return $this->severity === 'error';
    }

    public function isWarning(): bool
    {
        return $this->severity === 'warning';
    }
}
