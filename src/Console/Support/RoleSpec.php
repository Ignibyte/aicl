<?php

declare(strict_types=1);

namespace Aicl\Console\Support;

/**
 * Represents a role definition in a *.permissions.md spec.
 *
 * Parsed from the ## Roles table:
 * | Role | Description | Guard |
 */
class RoleSpec
{
    /**
     * @param array<int, string> $guards Guard names (e.g., ['web', 'api'])
     */
    public function __construct(
        public string $name,
        public string $description = '',
        public array $guards = ['web'],
    ) {}
}
