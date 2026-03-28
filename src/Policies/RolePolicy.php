<?php

declare(strict_types=1);

namespace Aicl\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;
use Spatie\Permission\Models\Role;

/**
 * Authorization policy for Spatie Role models.
 *
 * Uses Shield permission format (Action:Role) for all standard CRUD
 * and bulk operations. Does not extend BasePolicy because Spatie's
 * Role model is not an Eloquent Model subclass compatible with the
 * base policy's type hints.
 */
class RolePolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view the roles index.
     *
     * @param  AuthUser  $authUser  The authenticated user
     */
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Role');
    }

    public function view(AuthUser $authUser, Role $role): bool
    {
        // @codeCoverageIgnoreStart — Authorization policy
        return $authUser->can('View:Role');
        // @codeCoverageIgnoreEnd
    }

    public function create(AuthUser $authUser): bool
    {
        // @codeCoverageIgnoreStart — Authorization policy
        return $authUser->can('Create:Role');
        // @codeCoverageIgnoreEnd
    }

    public function update(AuthUser $authUser, Role $role): bool
    {
        // @codeCoverageIgnoreStart — Authorization policy
        return $authUser->can('Update:Role');
        // @codeCoverageIgnoreEnd
    }

    public function delete(AuthUser $authUser, Role $role): bool
    {
        // @codeCoverageIgnoreStart — Authorization policy
        return $authUser->can('Delete:Role');
        // @codeCoverageIgnoreEnd
    }

    public function restore(AuthUser $authUser, Role $role): bool
    {
        // @codeCoverageIgnoreStart — Authorization policy
        return $authUser->can('Restore:Role');
        // @codeCoverageIgnoreEnd
    }

    public function forceDelete(AuthUser $authUser, Role $role): bool
    {
        // @codeCoverageIgnoreStart — Authorization policy
        return $authUser->can('ForceDelete:Role');
        // @codeCoverageIgnoreEnd
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        // @codeCoverageIgnoreStart — Authorization policy
        return $authUser->can('ForceDeleteAny:Role');
        // @codeCoverageIgnoreEnd
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        // @codeCoverageIgnoreStart — Authorization policy
        return $authUser->can('RestoreAny:Role');
        // @codeCoverageIgnoreEnd
    }

    public function replicate(AuthUser $authUser, Role $role): bool
    {
        // @codeCoverageIgnoreStart — Authorization policy
        return $authUser->can('Replicate:Role');
        // @codeCoverageIgnoreEnd
    }

    public function reorder(AuthUser $authUser): bool
    {
        // @codeCoverageIgnoreStart — Authorization policy
        return $authUser->can('Reorder:Role');
        // @codeCoverageIgnoreEnd
    }
}
