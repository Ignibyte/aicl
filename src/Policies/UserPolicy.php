<?php

namespace Aicl\Policies;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User;

/**
 * Authorization policy for User models.
 *
 * Extends BasePolicy with self-view and self-update bypasses: users
 * can always view and update their own profile without needing explicit
 * View:User or Update:User permissions.
 */
class UserPolicy extends BasePolicy
{
    /**
     * {@inheritdoc}
     */
    protected function permissionPrefix(): string
    {
        return 'User';
    }

    /**
     * Users can always view their own profile.
     */
    public function view(User $user, Model $record): bool
    {
        if ($user->getKey() === $record->getKey()) {
            return true;
        }

        return parent::view($user, $record);
    }

    /**
     * Users can always update their own profile.
     */
    public function update(User $user, Model $record): bool
    {
        if ($user->getKey() === $record->getKey()) {
            return true;
        }

        return parent::update($user, $record);
    }
}
