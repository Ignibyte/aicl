<?php

namespace Aicl\AI;

use Aicl\AI\Contracts\AiTool;
use Aicl\AI\Tools\BaseTool;
use Aicl\Models\AiAgent;
use Illuminate\Contracts\Container\Container;

class AiToolRegistry
{
    /** @var array<string, class-string<AiTool>> */
    protected array $tools = [];

    public function __construct(
        private readonly Container $container,
    ) {}

    /**
     * Register a tool class.
     *
     * @param  class-string<AiTool>  $toolClass
     */
    public function register(string $toolClass): void
    {
        if (! in_array($toolClass, $this->tools, true)) {
            $this->tools[$toolClass] = $toolClass;
        }
    }

    /**
     * Register multiple tool classes at once.
     *
     * @param  array<class-string<AiTool>>  $toolClasses
     */
    public function registerMany(array $toolClasses): void
    {
        foreach ($toolClasses as $toolClass) {
            $this->register($toolClass);
        }
    }

    /**
     * Resolve all registered tools into instances.
     * Optionally inject auth context for tools that require it.
     *
     * @return array<AiTool>
     */
    public function resolve(?int $userId = null): array
    {
        $instances = [];

        foreach ($this->tools as $toolClass) {
            /** @var AiTool $tool */
            $tool = $this->container->make($toolClass);

            if ($userId !== null && $tool->requiresAuth() && $tool instanceof BaseTool) {
                $tool->setAuthenticatedUser($userId);
            }

            $instances[] = $tool;
        }

        return $instances;
    }

    /**
     * Resolve tools scoped to a specific agent's capabilities.
     *
     * If the agent has tools disabled, returns empty array.
     * If the agent has an allowed_tools list, only those tools are returned.
     * If allowed_tools is null (unrestricted), all registered tools are returned.
     *
     * @return array<AiTool>
     */
    public function resolveForAgent(AiAgent $agent, ?int $userId = null): array
    {
        if (! $agent->hasToolsEnabled()) {
            return [];
        }

        $allowedTools = $agent->getAllowedTools();

        // null = all tools allowed
        if ($allowedTools === null) {
            return $this->resolve($userId);
        }

        // Filter to only allowed tool classes
        $instances = [];

        foreach ($this->tools as $toolClass) {
            if (! in_array($toolClass, $allowedTools, true)) {
                continue;
            }

            /** @var AiTool $tool */
            $tool = $this->container->make($toolClass);

            if ($userId !== null && $tool->requiresAuth() && $tool instanceof BaseTool) {
                $tool->setAuthenticatedUser($userId);
            }

            $instances[] = $tool;
        }

        return $instances;
    }

    /**
     * List registered tool class names (for introspection/config).
     *
     * @return array<string, class-string<AiTool>>
     */
    public function registered(): array
    {
        return $this->tools;
    }
}
