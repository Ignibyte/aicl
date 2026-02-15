<?php

namespace Aicl\AI;

use Aicl\AI\Contracts\AiTool;
use Aicl\AI\Tools\BaseTool;
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
     * List registered tool class names (for introspection/config).
     *
     * @return array<string, class-string<AiTool>>
     */
    public function registered(): array
    {
        return $this->tools;
    }
}
