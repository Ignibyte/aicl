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
        public string $introducedIn = 'v1',
        public ?string $removedIn = null,
    ) {}

    public function isError(): bool
    {
        return $this->severity === 'error';
    }

    public function isWarning(): bool
    {
        return $this->severity === 'warning';
    }

    /**
     * Check if this pattern is active in the given version.
     */
    public function isActiveInVersion(string $version): bool
    {
        if (version_compare($version, $this->introducedIn, '<')) {
            return false;
        }

        if ($this->removedIn !== null && version_compare($version, $this->removedIn, '>=')) {
            return false;
        }

        return true;
    }
}
