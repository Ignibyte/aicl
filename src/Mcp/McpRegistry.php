<?php

namespace Aicl\Mcp;

use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Server\Tool;

class McpRegistry
{
    /** @var array<int, class-string<Tool>> */
    protected array $tools = [];

    /** @var array<int, class-string<\Laravel\Mcp\Server\Resource>> */
    protected array $resources = [];

    /** @var array<int, class-string<Prompt>> */
    protected array $prompts = [];

    /**
     * Register a tool class.
     *
     * @param  class-string<Tool>  $class
     */
    public function registerTool(string $class): void
    {
        if (! in_array($class, $this->tools, true)) {
            $this->tools[] = $class;
        }
    }

    /**
     * Register a resource class.
     *
     * @param  class-string<\Laravel\Mcp\Server\Resource>  $class
     */
    public function registerResource(string $class): void
    {
        if (! in_array($class, $this->resources, true)) {
            $this->resources[] = $class;
        }
    }

    /**
     * Register a prompt class.
     *
     * @param  class-string<Prompt>  $class
     */
    public function registerPrompt(string $class): void
    {
        if (! in_array($class, $this->prompts, true)) {
            $this->prompts[] = $class;
        }
    }

    /**
     * Register multiple primitive classes at once.
     *
     * @param  array{tools?: array<class-string<Tool>>, resources?: array<class-string<\Laravel\Mcp\Server\Resource>>, prompts?: array<class-string<Prompt>>}  $classes
     */
    public function registerMany(array $classes): void
    {
        foreach ($classes['tools'] ?? [] as $tool) {
            $this->registerTool($tool);
        }

        foreach ($classes['resources'] ?? [] as $resource) {
            $this->registerResource($resource);
        }

        foreach ($classes['prompts'] ?? [] as $prompt) {
            $this->registerPrompt($prompt);
        }
    }

    /**
     * Get all registered tool classes.
     *
     * @return array<int, class-string<Tool>>
     */
    public function tools(): array
    {
        return $this->tools;
    }

    /**
     * Get all registered resource classes.
     *
     * @return array<int, class-string<\Laravel\Mcp\Server\Resource>>
     */
    public function resources(): array
    {
        return $this->resources;
    }

    /**
     * Get all registered prompt classes.
     *
     * @return array<int, class-string<Prompt>>
     */
    public function prompts(): array
    {
        return $this->prompts;
    }
}
