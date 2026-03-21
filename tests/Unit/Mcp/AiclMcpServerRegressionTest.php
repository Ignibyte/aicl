<?php

declare(strict_types=1);

namespace Aicl\Tests\Unit\Mcp;

use Aicl\Mcp\AiclMcpServer;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Transport\FakeTransporter;
use Tests\TestCase;

/**
 * Regression tests for AiclMcpServer PHPStan changes.
 *
 * This is a new file introduced during the MCP server implementation.
 * Tests the server boot process, entity tool registration, custom tool
 * auto-discovery, resource registration, and prompt registration.
 */
class AiclMcpServerRegressionTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        // Enable MCP feature for tests
        config(['aicl.features.mcp' => true]);
    }

    // -- Server construction --

    /**
     * Test server extends Laravel MCP Server base class.
     */
    public function test_extends_mcp_server(): void
    {
        // Assert: verify parent class via reflection
        $reflection = new \ReflectionClass(AiclMcpServer::class);
        $parentClass = $reflection->getParentClass();
        $this->assertNotFalse($parentClass);
        $this->assertSame(Server::class, $parentClass->getName());
    }

    /**
     * Test server can be constructed with FakeTransporter.
     *
     * Verifies the constructor accepts a transport instance.
     */
    public function test_can_construct_with_fake_transport(): void
    {
        // Arrange
        $transport = new FakeTransporter;

        // Act
        $server = new AiclMcpServer($transport);

        // Assert: server created without error
        $this->assertInstanceOf(AiclMcpServer::class, $server);
    }

    // -- Boot and tool registration --

    /**
     * Test server registers tools during boot.
     *
     * After start(), the server's context should contain registered tools.
     */
    public function test_server_registers_tools_on_start(): void
    {
        // Arrange
        $transport = new FakeTransporter;
        $server = new AiclMcpServer($transport);

        // Act
        $server->start();
        $context = $server->createContext();
        $tools = $context->tools();

        // Assert: tools are registered (at least entity tools)
        $this->assertNotEmpty($tools);
    }

    /**
     * Test server registers resources during boot.
     *
     * The server should register EntityListResource and EntitySchemaResource.
     */
    public function test_server_registers_resources_on_start(): void
    {
        // Arrange
        $transport = new FakeTransporter;
        $server = new AiclMcpServer($transport);

        // Act
        $server->start();
        $context = $server->createContext();
        $resources = $context->resources();

        // Assert: resources are registered
        $this->assertNotEmpty($resources);
    }

    /**
     * Test server registers prompts during boot.
     *
     * The server should register CrudWorkflowPrompt and InspectEntityPrompt.
     */
    public function test_server_registers_prompts_on_start(): void
    {
        // Arrange
        $transport = new FakeTransporter;
        $server = new AiclMcpServer($transport);

        // Act
        $server->start();
        $context = $server->createContext();
        $prompts = $context->prompts();

        // Assert: prompts are registered
        $this->assertNotEmpty($prompts);
    }

    // -- Disabled MCP feature --

    /**
     * Test server handles MCP feature disabled gracefully.
     *
     * When aicl.features.mcp is false, the server should still construct
     * but not register entity tools.
     */
    public function test_server_handles_disabled_feature(): void
    {
        // Arrange: disable MCP
        config(['aicl.features.mcp' => false]);
        $transport = new FakeTransporter;
        $server = new AiclMcpServer($transport);

        // Act: start the server (should not throw)
        $server->start();

        // Assert: server created without error
        $this->assertInstanceOf(AiclMcpServer::class, $server);
    }
}
