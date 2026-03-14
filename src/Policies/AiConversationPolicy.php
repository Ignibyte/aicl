<?php

namespace Aicl\Policies;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User;

class AiConversationPolicy extends BasePolicy
{
    protected function permissionPrefix(): string
    {
        return 'AiConversation';
    }

    /**
     * Users can view their own conversations; admins can view all.
     */
    public function view(User $user, Model $record): bool
    {
        if ($user->can('ViewAny:AiConversation')) {
            return true;
        }

        /** @phpstan-ignore-next-line property.notFound */
        return $record->user_id === $user->id;
    }

    /**
     * Users can update their own conversations.
     */
    public function update(User $user, Model $record): bool
    {
        if ($user->can('Update:AiConversation')) {
            return true;
        }

        /** @phpstan-ignore-next-line property.notFound */
        return $record->user_id === $user->id;
    }

    /**
     * Users can delete their own conversations.
     */
    public function delete(User $user, Model $record): bool
    {
        if ($user->can('Delete:AiConversation')) {
            return true;
        }

        /** @phpstan-ignore-next-line property.notFound */
        return $record->user_id === $user->id;
    }
}
