<?php

namespace Aicl\AI\Tools;

use Aicl\AI\Enums\ToolRenderType;
use Aicl\Services\PresenceRegistry;

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
        return 'system';
    }

    public function renderAs(): ToolRenderType
    {
        return ToolRenderType::Table;
    }

    /**
     * @return array{type: string, data: array{columns: array<string>, rows: array<int, array<string, mixed>>}}
     */
    public function formatResultForDisplay(mixed $result): array
    {
        if (is_string($result)) {
            return ['type' => ToolRenderType::Text->value, 'data' => $result];
        }

        return [
            'type' => ToolRenderType::Table->value,
            'data' => [
                'columns' => ['User', 'Role', 'Last Seen', 'IP'],
                'rows' => collect($result)->map(fn (array $s): array => [
                    'User' => $s['user'] ?? 'Unknown',
                    'Role' => $s['role'] ?? '-',
                    'Last Seen' => $s['last_seen'] ?? '-',
                    'IP' => $s['ip'] ?? '-',
                ])->toArray(),
            ],
        ];
    }

    /**
     * @return string|array<int, array<string, mixed>>
     */
    public function __invoke(): string|array
    {
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
    }
}
