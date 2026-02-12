<?php

namespace Aicl\Policies;

use Aicl\Models\PreventionRule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User;

/**
 * Policy for PreventionRule entity.
 *
 * - Owner always has access to their own records
 * - Falls back to Shield permission checks via BasePolicy
 */
class PreventionRulePolicy extends BasePolicy
{
    protected function permissionPrefix(): string
    {
        return 'PreventionRule';
    }

    public function view(User $user, Model $record): bool
    {
        /** @var PreventionRule $record */
        if ($record->owner_id === $user->getKey()) {
            return true;
        }

        return parent::view($user, $record);
    }

    public function update(User $user, Model $record): bool
    {
        /** @var PreventionRule $record */
        if ($record->owner_id === $user->getKey()) {
            return true;
        }

        return parent::update($user, $record);
    }

    public function delete(User $user, Model $record): bool
    {
        /** @var PreventionRule $record */
        if ($record->owner_id === $user->getKey()) {
            return true;
        }

        return parent::delete($user, $record);
    }
}
