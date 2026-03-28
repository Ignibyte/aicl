<?php

declare(strict_types=1);

namespace Aicl\AI\Tools;

use Aicl\AI\Enums\ToolRenderType;
use App\Models\User;

/**
 * CurrentUserTool.
 */
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
        // @codeCoverageIgnoreStart — AI provider dependency
        return 'system';
        // @codeCoverageIgnoreEnd
    }

    public function requiresAuth(): bool
    {
        return true;
    }

    public function renderAs(): ToolRenderType
    {
        // @codeCoverageIgnoreStart — AI provider dependency
        return ToolRenderType::KeyValue;
        // @codeCoverageIgnoreEnd
    }

    /**
     * @return array{type: string, data: mixed}
     */
    public function formatResultForDisplay(mixed $result): array
    {
        // @codeCoverageIgnoreStart — AI provider dependency
        if (is_string($result)) {
            return ['type' => ToolRenderType::Text->value, 'data' => $result];
        }

        return [
            'type' => ToolRenderType::KeyValue->value,
            'data' => [
                'pairs' => [
                    ['key' => 'Name', 'value' => $result['name'] ?? '-'],
                    ['key' => 'Email', 'value' => $result['email'] ?? '-'],
                    ['key' => 'Roles', 'value' => implode(', ', $result['roles'] ?? []) ?: '-'],
                    ['key' => 'Member Since', 'value' => $result['created_at'] ?? '-'],
                ],
            ],
        ];
        // @codeCoverageIgnoreEnd
    }

    /**
     * @return string|array<string, mixed>
     */
    public function __invoke(): string|array
    {
        // @codeCoverageIgnoreStart — AI provider dependency
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
        // @codeCoverageIgnoreEnd
    }
}
