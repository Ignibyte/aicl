<?php

declare(strict_types=1);

namespace Aicl\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User;

/**
 * Base policy for all AICL entities.
 *
 * Shield's Gate `before` callback handles super_admin bypass automatically.
 * This base class provides permission-based authorization using spatie/laravel-permission.
 * AI-generated policies extend this class and override methods for entity-specific rules.
 *
 * Permission format: "{Action}:{Prefix}" (Shield's default pascal case with : separator)
 * Example: "ViewAny:Project", "Create:Project", "Delete:Project"
 */
abstract class BasePolicy
{
    use HandlesAuthorization;

    /**
     * The permission prefix for this policy (e.g., 'User', 'Project').
     * Shield generates permissions as "{Action}:{prefix}" (pascal case).
     */
    abstract protected function permissionPrefix(): string;

    /**
     * Determine whether the user can view the model index.
     *
     * @param  User  $user  The authenticated user
     */
    public function viewAny(User $user): bool
    {
        return $user->can('ViewAny:'.$this->permissionPrefix());
    }

    /**
     * Determine whether the user can view a specific record.
     *
     * @param  User  $user  The authenticated user
     * @param  Model  $record  The model instance being accessed
     */
    public function view(User $user, Model $record): bool
    {
        return $user->can('View:'.$this->permissionPrefix());
    }

    /**
     * Determine whether the user can create new records.
     *
     * @param  User  $user  The authenticated user
     */
    public function create(User $user): bool
    {
        return $user->can('Create:'.$this->permissionPrefix());
    }

    /**
     * Determine whether the user can update the given record.
     *
     * @param  User  $user  The authenticated user
     * @param  Model  $record  The model instance being updated
     */
    public function update(User $user, Model $record): bool
    {
        return $user->can('Update:'.$this->permissionPrefix());
    }

    /**
     * Determine whether the user can delete the given record.
     *
     * @param  User  $user  The authenticated user
     * @param  Model  $record  The model instance being deleted
     */
    public function delete(User $user, Model $record): bool
    {
        return $user->can('Delete:'.$this->permissionPrefix());
    }

    /**
     * Determine whether the user can restore the given soft-deleted record.
     *
     * @param  User  $user  The authenticated user
     * @param  Model  $record  The soft-deleted model instance
     */
    public function restore(User $user, Model $record): bool
    {
        // @codeCoverageIgnoreStart — Authorization policy
        return $user->can('Restore:'.$this->permissionPrefix());
        // @codeCoverageIgnoreEnd
    }

    /**
     * Determine whether the user can permanently delete the given record.
     *
     * @param  User  $user  The authenticated user
     * @param  Model  $record  The model instance being force-deleted
     */
    public function forceDelete(User $user, Model $record): bool
    {
        // @codeCoverageIgnoreStart — Authorization policy
        return $user->can('ForceDelete:'.$this->permissionPrefix());
        // @codeCoverageIgnoreEnd
    }

    /**
     * Determine whether the user can restore any soft-deleted records.
     *
     * @param  User  $user  The authenticated user
     */
    public function restoreAny(User $user): bool
    {
        // @codeCoverageIgnoreStart — Authorization policy
        return $user->can('RestoreAny:'.$this->permissionPrefix());
        // @codeCoverageIgnoreEnd
    }

    /**
     * Determine whether the user can permanently delete any records.
     *
     * @param  User  $user  The authenticated user
     */
    public function forceDeleteAny(User $user): bool
    {
        // @codeCoverageIgnoreStart — Authorization policy
        return $user->can('ForceDeleteAny:'.$this->permissionPrefix());
        // @codeCoverageIgnoreEnd
    }

    /**
     * Determine whether the user can replicate the given record.
     *
     * @param  User  $user  The authenticated user
     * @param  Model  $record  The model instance being replicated
     */
    public function replicate(User $user, Model $record): bool
    {
        // @codeCoverageIgnoreStart — Authorization policy
        return $user->can('Replicate:'.$this->permissionPrefix());
        // @codeCoverageIgnoreEnd
    }

    /**
     * Determine whether the user can reorder records.
     *
     * @param  User  $user  The authenticated user
     */
    public function reorder(User $user): bool
    {
        // @codeCoverageIgnoreStart — Authorization policy
        return $user->can('Reorder:'.$this->permissionPrefix());
        // @codeCoverageIgnoreEnd
    }
}
