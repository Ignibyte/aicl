<?php

// PATTERN: Policy extends BasePolicy which handles Shield permission checks.
// PATTERN: Override specific methods to add entity-specific ownership logic.
// PATTERN: permissionPrefix() returns the entity name for Shield permission lookup.

namespace App\Policies;

use App\Models\Project;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User;

/**
 * PATTERN: PHPDoc explains the authorization rules in plain English.
 *
 * - Owner can always view and edit their own projects
 * - Members can view but not edit
 * - Standard Shield permission checks for everything else
 */
class ProjectPolicy extends BasePolicy
{
    // PATTERN: permissionPrefix() returns the Shield permission prefix.
    // Shield format is "Action:Entity" (e.g., "ViewAny:Project").
    protected function permissionPrefix(): string
    {
        return 'Project';
    }

    // PATTERN: Override view/update/delete to add ownership checks BEFORE falling back to permissions.
    public function view(User $user, Model $record): bool
    {
        /** @var Project $record */
        // PATTERN: Owner always has access.
        if ($record->owner_id === $user->getKey()) {
            return true;
        }

        // PATTERN: Members can view (many-to-many check).
        if ($record->members()->where('user_id', $user->getKey())->exists()) {
            return true;
        }

        // PATTERN: Fall back to Shield permission check.
        return parent::view($user, $record);
    }

    public function update(User $user, Model $record): bool
    {
        /** @var Project $record */
        if ($record->owner_id === $user->getKey()) {
            return true;
        }

        return parent::update($user, $record);
    }

    public function delete(User $user, Model $record): bool
    {
        /** @var Project $record */
        if ($record->owner_id === $user->getKey()) {
            return true;
        }

        return parent::delete($user, $record);
    }
}
