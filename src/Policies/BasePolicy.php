<?php

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

    public function viewAny(User $user): bool
    {
        return $user->can('ViewAny:'.$this->permissionPrefix());
    }

    public function view(User $user, Model $record): bool
    {
        return $user->can('View:'.$this->permissionPrefix());
    }

    public function create(User $user): bool
    {
        return $user->can('Create:'.$this->permissionPrefix());
    }

    public function update(User $user, Model $record): bool
    {
        return $user->can('Update:'.$this->permissionPrefix());
    }

    public function delete(User $user, Model $record): bool
    {
        return $user->can('Delete:'.$this->permissionPrefix());
    }

    public function restore(User $user, Model $record): bool
    {
        return $user->can('Restore:'.$this->permissionPrefix());
    }

    public function forceDelete(User $user, Model $record): bool
    {
        return $user->can('ForceDelete:'.$this->permissionPrefix());
    }

    public function restoreAny(User $user): bool
    {
        return $user->can('RestoreAny:'.$this->permissionPrefix());
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->can('ForceDeleteAny:'.$this->permissionPrefix());
    }

    public function replicate(User $user, Model $record): bool
    {
        return $user->can('Replicate:'.$this->permissionPrefix());
    }

    public function reorder(User $user): bool
    {
        return $user->can('Reorder:'.$this->permissionPrefix());
    }
}
