<?php

declare(strict_types=1);

namespace Aicl\Tests\Unit\AI;

use Aicl\AI\AiToolRegistry;
use Aicl\AI\Tools\BaseTool;
use Aicl\AI\Tools\CurrentUserTool;
use Aicl\AI\Tools\EntityCountTool;
use Aicl\AI\Tools\HealthStatusTool;
use Aicl\AI\Tools\WhosOnlineTool;
use Tests\TestCase;

/**
 * Tests the AiToolRegistry including registration, resolution,
 * user ID injection, and auto-discovery of tool classes.
 */
class AiToolRegistryTest extends TestCase
{
    public function test_register_adds_tool_class(): void
    {
        $registry = new AiToolRegistry($this->app);

        $registry->register(WhosOnlineTool::class);

        $registered = $registry->registered();
        $this->assertCount(1, $registered);
        $this->assertContains(WhosOnlineTool::class, $registered);
    }

    public function test_register_many_adds_multiple_tool_classes(): void
    {
        $registry = new AiToolRegistry($this->app);

        $registry->registerMany([
            WhosOnlineTool::class,
            CurrentUserTool::class,
            HealthStatusTool::class,
        ]);

        $registered = $registry->registered();
        $this->assertCount(3, $registered);
        $this->assertContains(WhosOnlineTool::class, $registered);
        $this->assertContains(CurrentUserTool::class, $registered);
        $this->assertContains(HealthStatusTool::class, $registered);
    }

    public function test_duplicate_registration_is_idempotent(): void
    {
        $registry = new AiToolRegistry($this->app);

        $registry->register(WhosOnlineTool::class);
        $registry->register(WhosOnlineTool::class);
        $registry->register(WhosOnlineTool::class);

        $registered = $registry->registered();
        $this->assertCount(1, $registered);
    }

    public function test_resolve_returns_tool_instances(): void
    {
        $registry = new AiToolRegistry($this->app);

        $registry->registerMany([
            WhosOnlineTool::class,
            EntityCountTool::class,
        ]);

        $tools = $registry->resolve();

        $this->assertCount(2, $tools);
        $this->assertInstanceOf(WhosOnlineTool::class, $tools[0]);
        $this->assertInstanceOf(EntityCountTool::class, $tools[1]);
    }

    public function test_resolve_with_user_id_injects_auth_on_requires_auth_tools(): void
    {
        $registry = new AiToolRegistry($this->app);

        $registry->registerMany([
            EntityCountTool::class,   // requiresAuth = false
            CurrentUserTool::class,   // requiresAuth = true
        ]);

        $tools = $registry->resolve(42);

        // EntityCountTool should NOT have auth injected
        /** @var BaseTool $entityCount */
        $entityCount = $tools[0];
        $this->assertNull($entityCount->getAuthenticatedUserId());

        // CurrentUserTool should have auth injected
        /** @var BaseTool $currentUser */
        $currentUser = $tools[1];
        $this->assertSame(42, $currentUser->getAuthenticatedUserId());
    }

    public function test_resolve_without_user_id_does_not_inject_auth(): void
    {
        $registry = new AiToolRegistry($this->app);

        $registry->register(CurrentUserTool::class);

        $tools = $registry->resolve();

        /** @var BaseTool $tool */
        $tool = $tools[0];
        $this->assertNull($tool->getAuthenticatedUserId());
    }

    public function test_registered_returns_class_map(): void
    {
        $registry = new AiToolRegistry($this->app);

        $registry->register(WhosOnlineTool::class);

        $registered = $registry->registered();

        $this->assertNotEmpty($registered);
        $this->assertSame(WhosOnlineTool::class, $registered[WhosOnlineTool::class]);
    }

    public function test_resolve_returns_empty_array_when_no_tools_registered(): void
    {
        $registry = new AiToolRegistry($this->app);

        $tools = $registry->resolve();

        $this->assertEmpty($tools);
    }

    public function test_resolve_returns_new_instances_each_time(): void
    {
        $registry = new AiToolRegistry($this->app);
        $registry->register(WhosOnlineTool::class);

        $tools1 = $registry->resolve();
        $tools2 = $registry->resolve();

        $this->assertNotSame($tools1[0], $tools2[0]);
    }
}
