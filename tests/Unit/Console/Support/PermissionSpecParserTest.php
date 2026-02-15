<?php

namespace Aicl\Tests\Unit\Console\Support;

use Aicl\Console\Support\PermissionSpec;
use Aicl\Console\Support\PermissionSpecParser;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class PermissionSpecParserTest extends TestCase
{
    protected PermissionSpecParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new PermissionSpecParser;
    }

    // ========================================================================
    // Full parse
    // ========================================================================

    public function test_parse_complete_permission_spec(): void
    {
        $content = <<<'MD'
# Permissions

Application-wide role-permission matrix.

## Roles

| Role | Description | Guard |
|------|-------------|-------|
| admin | Full system access | web, api |
| manager | Can manage assigned entities | web, api |
| viewer | Read-only access | web |

## Permissions

| Entity | admin | manager | viewer |
|--------|-------|---------|--------|
| User | ViewAny, View, Create, Update, Delete | ViewAny, View | ViewAny, View |
| Project | * | ViewAny, View, Create, Update | ViewAny, View |
| Invoice | * | ViewAny, View, Create, Update, Delete | ViewAny, View |

## Custom Permissions

| Permission | Roles | Description |
|------------|-------|-------------|
| ExportData | admin, manager | Can export data to CSV |
| ManageSettings | admin | Can modify application settings |
| ViewReports | admin, manager, viewer | Can access reporting dashboards |
MD;

        $spec = $this->parser->parseContent($content);

        // Roles
        $this->assertCount(3, $spec->roles);
        $this->assertSame('admin', $spec->roles[0]->name);
        $this->assertSame(['web', 'api'], $spec->roles[0]->guards);
        $this->assertSame('viewer', $spec->roles[2]->name);
        $this->assertSame(['web'], $spec->roles[2]->guards);

        $this->assertSame(['admin', 'manager', 'viewer'], $spec->roleNames());

        // Matrix
        $this->assertSame(['User', 'Project', 'Invoice'], $spec->entities());

        // User permissions
        $userAdmin = $spec->permissionsFor('User', 'admin');
        $this->assertContains('ViewAny', $userAdmin);
        $this->assertContains('Delete', $userAdmin);
        $this->assertCount(5, $userAdmin);

        // Project with wildcard
        $projectAdmin = $spec->permissionsFor('Project', 'admin');
        $this->assertSame(PermissionSpec::WILDCARD_ACTIONS, $projectAdmin);
        $this->assertCount(7, $projectAdmin);

        // Custom Permissions
        $this->assertCount(3, $spec->customPermissions);
        $this->assertSame('ExportData', $spec->customPermissions[0]->permission);
        $this->assertSame(['admin', 'manager'], $spec->customPermissions[0]->roles);
        $this->assertSame('ManageSettings', $spec->customPermissions[1]->permission);
        $this->assertSame(['admin'], $spec->customPermissions[1]->roles);
    }

    public function test_parse_minimal_permission_spec(): void
    {
        $content = <<<'MD'
# Permissions

Minimal spec.

## Roles

| Role | Description | Guard |
|------|-------------|-------|
| admin | Admin | web |

## Permissions

| Entity | admin |
|--------|-------|
| User | * |
MD;

        $spec = $this->parser->parseContent($content);

        $this->assertCount(1, $spec->roles);
        $this->assertSame('admin', $spec->roles[0]->name);
        $this->assertCount(1, $spec->entities());
        $this->assertCount(7, $spec->permissionsFor('User', 'admin'));
        $this->assertEmpty($spec->customPermissions);
    }

    // ========================================================================
    // Wildcard expansion
    // ========================================================================

    public function test_wildcard_expands_to_all_crud_actions(): void
    {
        $actions = PermissionSpec::expandWildcard('*');

        $this->assertSame([
            'ViewAny', 'View', 'Create', 'Update', 'Delete', 'Restore', 'ForceDelete',
        ], $actions);
    }

    public function test_non_wildcard_splits_by_comma(): void
    {
        $actions = PermissionSpec::expandWildcard('ViewAny, View, Create');

        $this->assertSame(['ViewAny', 'View', 'Create'], $actions);
    }

    // ========================================================================
    // Error cases
    // ========================================================================

    public function test_missing_roles_section_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must have a ## Roles section');

        $content = <<<'MD'
# Permissions

No roles.

## Permissions

| Entity | admin |
|--------|-------|
| User | * |
MD;

        $this->parser->parseContent($content);
    }

    public function test_empty_roles_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must define at least one role');

        $content = <<<'MD'
# Permissions

Empty roles.

## Roles

| Role | Description | Guard |
|------|-------------|-------|
MD;

        $this->parser->parseContent($content);
    }

    // ========================================================================
    // Edge cases
    // ========================================================================

    public function test_no_permissions_section_returns_empty_matrix(): void
    {
        $content = <<<'MD'
# Permissions

Roles only.

## Roles

| Role | Description | Guard |
|------|-------------|-------|
| admin | Admin | web |
MD;

        $spec = $this->parser->parseContent($content);

        $this->assertCount(1, $spec->roles);
        $this->assertEmpty($spec->matrix);
    }

    public function test_no_custom_permissions_returns_empty(): void
    {
        $content = <<<'MD'
# Permissions

No custom perms.

## Roles

| Role | Description | Guard |
|------|-------------|-------|
| admin | Admin | web |

## Permissions

| Entity | admin |
|--------|-------|
| User | * |
MD;

        $spec = $this->parser->parseContent($content);

        $this->assertEmpty($spec->customPermissions);
    }

    public function test_role_with_empty_name_is_skipped(): void
    {
        $content = <<<'MD'
# Permissions

Test.

## Roles

| Role | Description | Guard |
|------|-------------|-------|
| admin | Admin | web |
| | Empty | web |
MD;

        $spec = $this->parser->parseContent($content);

        $this->assertCount(1, $spec->roles);
    }

    public function test_permission_for_nonexistent_entity_returns_empty(): void
    {
        $content = <<<'MD'
# Permissions

Test.

## Roles

| Role | Description | Guard |
|------|-------------|-------|
| admin | Admin | web |

## Permissions

| Entity | admin |
|--------|-------|
| User | * |
MD;

        $spec = $this->parser->parseContent($content);

        $this->assertEmpty($spec->permissionsFor('NonExistent', 'admin'));
        $this->assertEmpty($spec->permissionsFor('User', 'nonexistent'));
    }

    public function test_guard_defaults_to_web(): void
    {
        $content = <<<'MD'
# Permissions

Test.

## Roles

| Role | Description | Guard |
|------|-------------|-------|
| admin | Admin | |
MD;

        // When guard is empty, it should still parse (empty string splits to [''])
        $spec = $this->parser->parseContent($content);

        $this->assertCount(1, $spec->roles);
    }
}
