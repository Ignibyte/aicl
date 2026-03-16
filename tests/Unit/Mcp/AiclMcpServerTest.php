<?php

namespace Aicl\Tests\Unit\Mcp;

use Aicl\Mcp\AiclMcpServer;
use Aicl\Mcp\Tools\CreateEntityTool;
use Aicl\Mcp\Tools\DeleteEntityTool;
use Aicl\Mcp\Tools\ListEntityTool;
use Aicl\Mcp\Tools\ShowEntityTool;
use Aicl\Mcp\Tools\TransitionEntityTool;
use Aicl\Mcp\Tools\UpdateEntityTool;
use Aicl\Services\EntityRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Mcp\Server\Transport\FakeTransporter;
use Tests\TestCase;

class AiclMcpServerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['aicl.features.mcp' => true]);

    }

    protected function createServer(): AiclMcpServer
    {
        $transport = new FakeTransporter;
        $server = new AiclMcpServer($transport);
        $server->start();

        return $server;
    }

    public function test_boot_registers_entity_tools_from_registry(): void
    {
        $server = $this->createServer();
        $context = $server->createContext();
        $tools = $context->tools();

        $this->assertNotEmpty($tools, 'Server should have registered entity tools');

        $hasListTool = $tools->contains(fn ($tool) => $tool instanceof ListEntityTool);
        $hasShowTool = $tools->contains(fn ($tool) => $tool instanceof ShowEntityTool);

        $this->assertTrue($hasListTool, 'Server should register ListEntityTool instances');
        $this->assertTrue($hasShowTool, 'Server should register ShowEntityTool instances');
    }

    public function test_boot_registers_all_tool_types_for_entity_with_status(): void
    {
        $server = $this->createServer();
        $context = $server->createContext();
        $tools = $context->tools();

        $toolTypes = [];
        foreach ($tools as $tool) {
            $toolTypes[get_class($tool)] = true;
        }

        $this->assertArrayHasKey(ListEntityTool::class, $toolTypes);
        $this->assertArrayHasKey(ShowEntityTool::class, $toolTypes);
        $this->assertArrayHasKey(CreateEntityTool::class, $toolTypes);
        $this->assertArrayHasKey(UpdateEntityTool::class, $toolTypes);
        $this->assertArrayHasKey(DeleteEntityTool::class, $toolTypes);
    }

    public function test_custom_tools_directory_gracefully_handles_missing_directory(): void
    {
        // The default app/Mcp/Tools directory shouldn't exist in test env
        // Server should boot without errors even when custom tools dir is missing
        $server = $this->createServer();
        $context = $server->createContext();

        $this->assertNotEmpty($context->tools());
    }

    public function test_server_context_has_correct_attribute_name(): void
    {
        // The #[Name('AICL MCP Server')] attribute on AiclMcpServer takes precedence
        // over any dynamic name set in boot() via $this->name
        $server = $this->createServer();
        $context = $server->createContext();

        $this->assertSame('AICL MCP Server', $context->serverName);
    }

    public function test_server_context_has_correct_version(): void
    {
        $server = $this->createServer();
        $context = $server->createContext();

        $this->assertSame('1.0.0', $context->serverVersion);
    }

    public function test_server_context_has_instructions(): void
    {
        $server = $this->createServer();
        $context = $server->createContext();

        // Instructions come from the parent Server default (no #[Instructions] attribute set)
        $this->assertStringContainsString('MCP server', $context->instructions);
        $this->assertStringContainsString('Laravel', $context->instructions);
    }

    public function test_boot_sets_dynamic_name_on_server_property(): void
    {
        // Verify the boot() method sets $this->name dynamically based on settings
        // Even though createContext() uses the attribute instead, boot() still runs
        config()->set('aicl.mcp.server_info.description', 'My Custom App');

        $server = $this->createServer();

        // Access protected name property via reflection to verify boot() ran
        $reflection = new \ReflectionProperty($server, 'name');
        $reflection->setAccessible(true);

        $this->assertSame('My Custom App MCP Server', $reflection->getValue($server));
    }

    public function test_boot_name_falls_back_to_config(): void
    {
        config()->set('aicl.mcp.server_info.description', null);
        config(['aicl.mcp.server_info.name' => 'Config App']);

        $server = $this->createServer();

        $reflection = new \ReflectionProperty($server, 'name');
        $reflection->setAccessible(true);

        $this->assertSame('Config App MCP Server', $reflection->getValue($server));
    }

    public function test_boot_name_falls_back_to_app_name(): void
    {
        config()->set('aicl.mcp.server_info.description', null);
        config(['aicl.mcp.server_info.name' => null]);
        config(['app.name' => 'TestApp']);

        $server = $this->createServer();

        $reflection = new \ReflectionProperty($server, 'name');
        $reflection->setAccessible(true);

        $this->assertSame('TestApp MCP Server', $reflection->getValue($server));
    }

    public function test_exposed_entities_wildcard_registers_all_entities(): void
    {
        config()->set('aicl.mcp.exposed_entities', ['*']);

        $server = $this->createServer();
        $context = $server->createContext();

        $registry = app(EntityRegistry::class);
        $entityCount = $registry->allTypes()->count();

        // With wildcard, each entity gets up to 6 tools
        $this->assertGreaterThanOrEqual($entityCount * 5, $context->tools()->count());
    }

    public function test_empty_exposed_entities_registers_no_tools(): void
    {
        config()->set('aicl.mcp.exposed_entities', []);

        $server = $this->createServer();
        $context = $server->createContext();

        $entityToolCount = $context->tools()->filter(function ($tool) {
            return $tool instanceof ListEntityTool
                || $tool instanceof ShowEntityTool
                || $tool instanceof CreateEntityTool
                || $tool instanceof UpdateEntityTool
                || $tool instanceof DeleteEntityTool
                || $tool instanceof TransitionEntityTool;
        })->count();

        $this->assertSame(0, $entityToolCount);
    }

    public function test_custom_tools_disabled_skips_directory_scan(): void
    {
        config()->set('aicl.mcp.custom_tools_enabled', false);

        $server = $this->createServer();
        $context = $server->createContext();

        $this->assertNotEmpty($context->tools());
    }
}
