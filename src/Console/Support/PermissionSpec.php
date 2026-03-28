<?php

declare(strict_types=1);

namespace Aicl\Console\Support;

/**
 * Value object representing a parsed *.permissions.md specification file.
 *
 * Contains roles, entity permission matrix, and custom permissions
 * needed to generate a complete RolePermissionSeeder.
 */
class PermissionSpec
{
    /**
     * Standard CRUD actions that `*` expands to.
     */
    public const WILDCARD_ACTIONS = [
        'ViewAny', 'View', 'Create', 'Update', 'Delete', 'Restore', 'ForceDelete',
    ];

    /**
     * @param  array<int, RoleSpec>  $roles  Role definitions
     * @param  array<string, array<string, array<int, string>>>  $matrix  Entity => Role => Permissions[]
     * @param  array<int, CustomPermissionSpec>  $customPermissions  Custom (non-entity) permissions
     */
    public function __construct(
        public array $roles = [],
        public array $matrix = [],
        public array $customPermissions = [],
    ) {}

    /**
     * Get all role names.
     *
     * @return array<int, string>
     */
    public function roleNames(): array
    {
        return array_map(fn (RoleSpec $r): string => $r->name, $this->roles);
    }

    /**
     * Get all entity names in the matrix.
     *
     * @return array<int, string>
     */
    public function entities(): array
    {
        return array_keys($this->matrix);
    }

    /**
     * Get permissions for a specific role and entity.
     *
     * @return array<int, string>
     */
    public function permissionsFor(string $entity, string $role): array
    {
        return $this->matrix[$entity][$role] ?? [];
    }

    /**
     * Expand a wildcard `*` to all standard CRUD actions.
     *
     * @return array<int, string>
     */
    public static function expandWildcard(string $permissions): array
    {
        $trimmed = trim($permissions);

        if ($trimmed === '*') {
            return self::WILDCARD_ACTIONS;
        }

        return array_map('trim', explode(',', $trimmed));
    }
}
