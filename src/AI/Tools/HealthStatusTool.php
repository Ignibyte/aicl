<?php

namespace Aicl\AI\Tools;

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
