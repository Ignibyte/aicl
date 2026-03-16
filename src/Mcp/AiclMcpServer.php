<?php

namespace Aicl\Mcp;

use Aicl\Mcp\Prompts\CrudWorkflowPrompt;
use Aicl\Mcp\Prompts\InspectEntityPrompt;
use Aicl\Mcp\Resources\EntityListResource;
use Aicl\Mcp\Resources\EntitySchemaResource;
use Aicl\Mcp\Tools\CreateEntityTool;
use Aicl\Mcp\Tools\DeleteEntityTool;
use Aicl\Mcp\Tools\ListEntityTool;
use Aicl\Mcp\Tools\ShowEntityTool;
use Aicl\Mcp\Tools\TransitionEntityTool;
use Aicl\Mcp\Tools\UpdateEntityTool;
use Aicl\Services\EntityRegistry;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Resource;

/**
 * AICL Model Context Protocol (MCP) server.
 *
 * Auto-discovers registered entities from the EntityRegistry and exposes them
 * as MCP tools (list, show, create, update, delete, transition), resources
 * (entity schemas), and prompts (CRUD workflow, inspect entity). Supports
 * selective entity exposure via the aicl.mcp.exposed_entities config.
 *
 * Also registers tools/resources/prompts from the McpRegistry (programmatic
 * contributions from other packages) and auto-discovers custom primitives
 * from app/Mcp/Tools/, app/Mcp/Resources/, and app/Mcp/Prompts/.
 *
 * @see McpRegistry  Programmatic registration of MCP primitives
 * @see EntityRegistry  Central registry of all entity types
 */
#[Name('AICL MCP Server')]
#[Version('1.0.0')]
#[Description('Exposes AICL application entities via Model Context Protocol for AI agent interaction.')]
class AiclMcpServer extends Server
{
    /**
     * Boot the MCP server by registering all tools, resources, and prompts.
     *
     * Sets the server display name from config, then registers entity-derived
     * tools and resources, built-in prompts, McpRegistry contributions,
     * and auto-discovered custom primitives.
     */
    protected function boot(): void
    {
        $serverName = config('aicl.mcp.server_info.description')
            ?? config('aicl.mcp.server_info.name')
            ?? config('app.name', 'AICL');

        $this->name = $serverName.' MCP Server';

        $this->registerEntityTools();
        $this->registerEntityResources();
        $this->registerPrompts();
        $this->registerFromMcpRegistry();
        $this->registerCustomTools();
        $this->registerCustomResources();
        $this->registerCustomPrompts();
    }

    /**
     * Register CRUD and transition tools for each exposed entity.
     *
     * Iterates all entity types from the EntityRegistry, resolves which
     * operations are enabled per entity (via config), and creates the
     * corresponding MCP tool instances (list, show, create, update, delete, transition).
     */
    protected function registerEntityTools(): void
    {
        /** @var EntityRegistry $registry */
        $registry = app(EntityRegistry::class);
        $entities = $registry->allTypes();

        $exposedConfig = config('aicl.mcp.exposed_entities', ['*']);
        $exposeAll = $exposedConfig === ['*'];

        foreach ($entities as $entry) {
            $class = $entry['class'];
            $operations = $this->resolveOperations($class, $exposedConfig, $exposeAll);

            if (empty($operations)) {
                continue;
            }

            $label = $entry['label'];
            $hasStatus = $entry['columns']['has_status'];

            if (in_array('list', $operations, true)) {
                $this->tools[] = new ListEntityTool($class, $label);
            }

            if (in_array('show', $operations, true)) {
                $this->tools[] = new ShowEntityTool($class, $label);
            }

            if (in_array('create', $operations, true)) {
                $this->tools[] = new CreateEntityTool($class, $label);
            }

            if (in_array('update', $operations, true)) {
                $this->tools[] = new UpdateEntityTool($class, $label);
            }

            if (in_array('delete', $operations, true)) {
                $this->tools[] = new DeleteEntityTool($class, $label);
            }

            if ($hasStatus && in_array('transitions', $operations, true)) {
                $this->tools[] = new TransitionEntityTool($class, $label);
            }
        }
    }

    /**
     * Register schema resources for each exposed entity.
     */
    protected function registerEntityResources(): void
    {
        /** @var EntityRegistry $registry */
        $registry = app(EntityRegistry::class);
        $entities = $registry->allTypes();

        $exposedConfig = config('aicl.mcp.exposed_entities', ['*']);
        $exposeAll = $exposedConfig === ['*'];

        foreach ($entities as $entry) {
            $class = $entry['class'];
            $operations = $this->resolveOperations($class, $exposedConfig, $exposeAll);

            if (empty($operations)) {
                continue;
            }

            $this->resources[] = new EntitySchemaResource($class, $entry['label']);
        }

        // Register the entity list resource template (always available)
        $this->resources[] = new EntityListResource;
    }

    /**
     * Register built-in workflow prompts.
     */
    protected function registerPrompts(): void
    {
        $this->prompts[] = new CrudWorkflowPrompt;
        $this->prompts[] = new InspectEntityPrompt;
    }

    /**
     * Pull tools, resources, and prompts contributed via the McpRegistry.
     */
    protected function registerFromMcpRegistry(): void
    {
        if (! app()->bound(McpRegistry::class)) {
            return;
        }

        /** @var McpRegistry $mcpRegistry */
        $mcpRegistry = app(McpRegistry::class);

        foreach ($mcpRegistry->tools() as $toolClass) {
            $this->tools[] = $toolClass;
        }

        foreach ($mcpRegistry->resources() as $resourceClass) {
            $this->resources[] = $resourceClass;
        }

        foreach ($mcpRegistry->prompts() as $promptClass) {
            $this->prompts[] = $promptClass;
        }
    }

    /**
     * Auto-discover custom tool classes from app/Mcp/Tools/.
     */
    protected function registerCustomTools(): void
    {
        if (! config('aicl.mcp.custom_tools_enabled', true)) {
            return;
        }

        $this->discoverCustomPrimitives(
            app_path('Mcp/Tools'),
            'App\\Mcp\\Tools',
            Server\Tool::class,
            $this->tools,
        );
    }

    /**
     * Auto-discover custom resource classes from app/Mcp/Resources/.
     */
    protected function registerCustomResources(): void
    {
        if (! config('aicl.mcp.custom_tools_enabled', true)) {
            return;
        }

        $this->discoverCustomPrimitives(
            app_path('Mcp/Resources'),
            'App\\Mcp\\Resources',
            Resource::class,
            $this->resources,
        );
    }

    /**
     * Auto-discover custom prompt classes from app/Mcp/Prompts/.
     */
    protected function registerCustomPrompts(): void
    {
        if (! config('aicl.mcp.custom_tools_enabled', true)) {
            return;
        }

        $this->discoverCustomPrimitives(
            app_path('Mcp/Prompts'),
            'App\\Mcp\\Prompts',
            Prompt::class,
            $this->prompts,
        );
    }

    /**
     * Scan a directory for classes extending the given base class and add them to the target array.
     *
     * @param  class-string  $baseClass
     * @param  array<int, mixed>  $target
     */
    protected function discoverCustomPrimitives(string $path, string $namespace, string $baseClass, array &$target): void
    {
        if (! is_dir($path)) {
            return;
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $relativePath = str_replace($path.'/', '', $file->getPathname());
            $className = $namespace.'\\'.str_replace(['/', '.php'], ['\\', ''], $relativePath);

            if (class_exists($className) && is_subclass_of($className, $baseClass)) {
                $target[] = $className;
            }
        }
    }

    /**
     * Resolve which operations are enabled for an entity.
     *
     * @param  class-string  $class
     * @param  array<string, array<string>>|array<string>  $exposedConfig
     * @return array<string>
     */
    protected function resolveOperations(string $class, array $exposedConfig, bool $exposeAll): array
    {
        $allOps = ['list', 'show', 'create', 'update', 'delete', 'transitions'];

        if ($exposeAll) {
            return $allOps;
        }

        return $exposedConfig[$class] ?? [];
    }
}
