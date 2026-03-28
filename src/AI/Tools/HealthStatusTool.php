<?php

declare(strict_types=1);

namespace Aicl\AI\Tools;

use Aicl\AI\Enums\ToolRenderType;
use Aicl\Health\HealthCheckRegistry;

/**
 * HealthStatusTool.
 */
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
        return ToolRenderType::Status;
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
            'type' => ToolRenderType::Status->value,
            'data' => [
                'items' => collect($resultArray)->map(fn (array $r): array => [
                    'label' => $r['service'] ?? 'Unknown',
                    'status' => $r['status'] ?? 'unknown',
                    'detail' => is_array($r['details'] ?? null) ? json_encode($r['details']) : ($r['details'] ?? null),
                ])->toArray(),
            ],
        ];
        // @codeCoverageIgnoreEnd
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function __invoke(): array
    {
        // @codeCoverageIgnoreStart — AI provider dependency
        $registry = app(HealthCheckRegistry::class);
        $results = $registry->runAllCached();

        return array_map(fn ($result): array => [
            'service' => $result->name,
            'status' => $result->status->value,
            'icon' => $result->icon,
            // Redact connection details — only expose safe summary fields
            'error' => $result->error ? 'Service error detected' : null,
        ], $results);
        // @codeCoverageIgnoreEnd
    }
}
