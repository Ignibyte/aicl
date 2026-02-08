<?php

namespace Aicl\Policies;

use Illuminate\Database\Eloquent\Model;

class UserPolicy extends BasePolicy
{
    protected function permissionPrefix(): string
    {
        return 'User';
    }

    /**
     * Users can always view their own profile.
     */
    public function view(\Illuminate\Foundation\Auth\User $user, Model $record): bool
    {
        if ($user->getKey() === $record->getKey()) {
            return true;
        }

        return parent::view($user, $record);
    }

    /**
     * Users can always update their own profile.
     */
    public function update(\Illuminate\Foundation\Auth\User $user, Model $record): bool
    {
        if ($user->getKey() === $record->getKey()) {
            return true;
        }

        return parent::update($user, $record);
    }
}
