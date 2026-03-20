<?php

declare(strict_types=1);

namespace Aicl\Console\Support;

use InvalidArgumentException;

/**
 * Parse a *.permissions.md specification file into a PermissionSpec value object.
 *
 * The spec file uses structured Markdown:
 * - # Permissions (or any title)
 * - ## Roles table (required)
 * - ## Permissions matrix table (required)
 * - ## Custom Permissions table (optional)
 */
class PermissionSpecParser
{
    /**
     * Parse a *.permissions.md file into a PermissionSpec.
     *
     * @throws InvalidArgumentException
     */
    public function parse(string $filePath): PermissionSpec
    {
        if (! file_exists($filePath)) {
            throw new InvalidArgumentException("Permission spec file not found: {$filePath}");
        }

        return $this->parseContent((string) file_get_contents($filePath));
    }

    /**
     * Parse raw Markdown content into a PermissionSpec.
     *
     * @throws InvalidArgumentException
     */
    public function parseContent(string $content): PermissionSpec
    {
        $sections = MarkdownTableParser::splitSections($content);

        $roles = $this->parseRoles($sections);
        $matrix = $this->parseMatrix($sections, $roles);
        $customPermissions = $this->parseCustomPermissions($sections);

        return new PermissionSpec(
            roles: $roles,
            matrix: $matrix,
            customPermissions: $customPermissions,
        );
    }

    /**
     * Parse ## Roles table.
     *
     * @param  array<string, string>  $sections
     * @return array<int, RoleSpec>
     *
     * @throws InvalidArgumentException
     */
    protected function parseRoles(array $sections): array
    {
        if (! isset($sections['Roles'])) {
            throw new InvalidArgumentException('Permission spec must have a ## Roles section.');
        }

        $rows = MarkdownTableParser::parseMarkdownTable($sections['Roles']);
        $roles = [];

        foreach ($rows as $row) {
            $name = trim($row['role'] ?? '');

            if ($name === '') {
                continue;
            }

            $description = trim($row['description'] ?? '');
            $guardStr = trim($row['guard'] ?? 'web');
            $guards = array_map('trim', explode(',', $guardStr));

            $roles[] = new RoleSpec(
                name: $name,
                description: $description,
                guards: $guards,
            );
        }

        if (empty($roles)) {
            throw new InvalidArgumentException('Permission spec ## Roles section must define at least one role.');
        }

        return $roles;
    }

    /**
     * Parse ## Permissions matrix table.
     *
     * The table has dynamic columns: Entity + one column per role.
     * `*` expands to all standard CRUD actions.
     *
     * @param  array<string, string>  $sections
     * @param  array<int, RoleSpec>  $roles
     * @return array<string, array<string, array<int, string>>>
     */
    protected function parseMatrix(array $sections, array $roles): array
    {
        if (! isset($sections['Permissions'])) {
            return [];
        }

        $rows = MarkdownTableParser::parseMarkdownTable($sections['Permissions']);
        $roleNames = array_map(fn (RoleSpec $r): string => strtolower($r->name), $roles);
        $matrix = [];

        foreach ($rows as $row) {
            $entity = trim($row['entity'] ?? '');

            if ($entity === '') {
                continue;
            }

            $entityPerms = [];

            foreach ($roles as $role) {
                $roleKey = strtolower($role->name);
                $permStr = trim($row[$roleKey] ?? '');

                if ($permStr === '') {
                    $entityPerms[$role->name] = [];

                    continue;
                }

                $entityPerms[$role->name] = PermissionSpec::expandWildcard($permStr);
            }

            $matrix[$entity] = $entityPerms;
        }

        return $matrix;
    }

    /**
     * Parse ## Custom Permissions table.
     *
     * @param  array<string, string>  $sections
     * @return array<int, CustomPermissionSpec>
     */
    protected function parseCustomPermissions(array $sections): array
    {
        if (! isset($sections['Custom Permissions'])) {
            return [];
        }

        $rows = MarkdownTableParser::parseMarkdownTable($sections['Custom Permissions']);
        $perms = [];

        foreach ($rows as $row) {
            $permission = trim($row['permission'] ?? '');

            if ($permission === '') {
                continue;
            }

            $rolesStr = trim($row['roles'] ?? '');
            $roles = array_map('trim', explode(',', $rolesStr));
            $description = trim($row['description'] ?? '');

            $perms[] = new CustomPermissionSpec(
                permission: $permission,
                roles: $roles,
                description: $description,
            );
        }

        return $perms;
    }
}
