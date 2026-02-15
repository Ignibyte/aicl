<?php

namespace Aicl\Console\Support;

/**
 * Represents a custom permission in a *.permissions.md spec.
 *
 * Parsed from the ## Custom Permissions table:
 * | Permission | Roles | Description |
 */
class CustomPermissionSpec
{
    /**
     * @param  array<int, string>  $roles  Role names that have this permission
     */
    public function __construct(
        public string $permission,
        public array $roles = [],
        public string $description = '',
    ) {}
}
