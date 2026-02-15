<?php

namespace Aicl\AI\Tools;

use App\Models\User;

class CurrentUserTool extends BaseTool
{
    public function __construct()
    {
        parent::__construct(
            name: 'current_user',
            description: 'Get information about the currently authenticated user, including their name, email, and roles.',
        );
    }

    public function category(): string
    {
        return 'system';
    }

    public function requiresAuth(): bool
    {
        return true;
    }

    /**
     * @return string|array<string, mixed>
     */
    public function __invoke(): string|array
    {
        if ($this->authenticatedUserId === null) {
            return 'No authenticated user context available.';
        }

        $user = User::find($this->authenticatedUserId);

        if (! $user) {
            return 'User not found.';
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'roles' => $user->getRoleNames()->toArray(),
            'created_at' => $user->created_at?->toIso8601String(),
        ];
    }
}
