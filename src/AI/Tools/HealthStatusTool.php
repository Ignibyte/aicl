<?php

namespace Aicl\AI\Tools;

use Aicl\AI\Enums\ToolRenderType;
use Aicl\Health\HealthCheckRegistry;

class HealthStatusTool extends BaseTool
{
    public function __construct()
    {
        parent::__construct(
            name: 'health_status',
            description: 'Get the current health status of all application services (database, cache, queue, Swoole, etc.). Returns service name, status (healthy/degraded/down), and details.',
        );
    }

    public function category(): string
    {
        return 'system';
    }

    public function renderAs(): ToolRenderType
    {
        return ToolRenderType::Status;
    }

    /**
     * @return array{type: string, data: array{items: array<int, array{label: string, status: string, detail: string|null}>}}
     */
    public function formatResultForDisplay(mixed $result): array
    {
        if (is_string($result)) {
            return ['type' => ToolRenderType::Text->value, 'data' => $result];
        }

        return [
            'type' => ToolRenderType::Status->value,
            'data' => [
                'items' => collect($result)->map(fn (array $r): array => [
                    'label' => $r['service'] ?? 'Unknown',
                    'status' => $r['status'] ?? 'unknown',
                    'detail' => is_array($r['details'] ?? null) ? json_encode($r['details']) : ($r['details'] ?? null),
                ])->toArray(),
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function __invoke(): array
    {
        $registry = app(HealthCheckRegistry::class);
        $results = $registry->runAllCached();

        return array_map(fn ($result): array => [
            'service' => $result->name,
            'status' => $result->status->value,
            'icon' => $result->icon,
            'details' => $result->details,
            'error' => $result->error,
        ], $results);
    }
}
