<?php

namespace Aicl\Policies;

use Aicl\Models\FailureReport;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User;

/**
 * Policy for FailureReport entity.
 *
 * - Owner always has access to their own records
 * - Falls back to Shield permission checks via BasePolicy
 */
class FailureReportPolicy extends BasePolicy
{
    protected function permissionPrefix(): string
    {
        return 'FailureReport';
    }

    public function view(User $user, Model $record): bool
    {
        /** @var FailureReport $record */
        if ($record->owner_id === $user->getKey()) {
            return true;
        }

        return parent::view($user, $record);
    }

    public function update(User $user, Model $record): bool
    {
        /** @var FailureReport $record */
        if ($record->owner_id === $user->getKey()) {
            return true;
        }

        return parent::update($user, $record);
    }

    public function delete(User $user, Model $record): bool
    {
        /** @var FailureReport $record */
        if ($record->owner_id === $user->getKey()) {
            return true;
        }

        return parent::delete($user, $record);
    }
}
