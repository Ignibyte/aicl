<?php

declare(strict_types=1);

namespace Aicl\AI\Tools;

use Aicl\AI\Enums\ToolRenderType;
use Aicl\Services\PresenceRegistry;

/**
 * WhosOnlineTool.
 */
class WhosOnlineTool extends BaseTool
{
    public function __construct()
    {
        parent::__construct(
            name: 'whos_online',
            description: 'Get a list of currently online users with their session info. Returns user names, roles, last activity time, and IP addresses.',
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
        return ToolRenderType::Table;
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

        /** @var array<int, array<string, mixed>> $resultArray */
        $resultArray = $result;

        return [
            'type' => ToolRenderType::Table->value,
            'data' => [
                'columns' => ['User', 'Role', 'Last Seen', 'IP'],
                'rows' => collect($resultArray)->map(fn (array $s): array => [
                    'User' => $s['user'] ?? 'Unknown',
                    'Role' => $s['role'] ?? '-',
                    'Last Seen' => $s['last_seen'] ?? '-',
                    'IP' => $s['ip'] ?? '-',
                ])->toArray(),
            ],
        ];
        // @codeCoverageIgnoreEnd
    }

    /**
     * @return string|array<int, array<string, mixed>>
     */
    public function __invoke(): string|array
    {
        // @codeCoverageIgnoreStart — AI provider dependency
        $registry = app(PresenceRegistry::class);
        $sessions = $registry->allSessions();

        if ($sessions->isEmpty()) {
            return 'No users are currently online.';
        }

        return $sessions->map(fn (array $s): array => [
            'user' => $s['user_name'] ?? 'Unknown',
            'role' => $s['role'] ?? null,
            'last_seen' => $s['last_seen_at'],
            'ip' => $s['ip_address'] ?? null,
        ])->values()->toArray();
        // @codeCoverageIgnoreEnd
    }
}
