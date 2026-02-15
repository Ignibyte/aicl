<?php

namespace Aicl\Tests\Unit\AI\Tools;

use Aicl\AI\Contracts\AiTool;
use Aicl\AI\Tools\BaseTool;
use Aicl\AI\Tools\EntityCountTool;
use Aicl\Services\EntityRegistry;
use Illuminate\Support\Collection;
use Mockery;
use Tests\TestCase;

class EntityCountToolTest extends TestCase
{
    public function test_implements_ai_tool_interface(): void
    {
        $tool = new EntityCountTool;

        $this->assertInstanceOf(AiTool::class, $tool);
        $this->assertInstanceOf(BaseTool::class, $tool);
    }

    public function test_has_correct_name(): void
    {
        $tool = new EntityCountTool;

        $this->assertSame('entity_count', $tool->getName());
    }

    public function test_category_is_queries(): void
    {
        $tool = new EntityCountTool;

        $this->assertSame('queries', $tool->category());
    }

    public function test_does_not_require_auth(): void
    {
        $tool = new EntityCountTool;

        $this->assertFalse($tool->requiresAuth());
    }

    public function test_returns_string_when_no_entity_types_registered(): void
    {
        $registry = Mockery::mock(EntityRegistry::class);
        $registry->shouldReceive('allTypes')->once()->andReturn(collect());

        $this->app->instance(EntityRegistry::class, $registry);

        $tool = new EntityCountTool;
        $result = $tool();

        $this->assertIsString($result);
        $this->assertSame('No entity types are registered in this application.', $result);
    }

    public function test_returns_unknown_entity_type_message(): void
    {
        $registry = Mockery::mock(EntityRegistry::class);
        $registry->shouldReceive('allTypes')->andReturn(new Collection([
            ['class' => 'App\\Models\\Task', 'table' => 'tasks', 'label' => 'Task'],
        ]));

        $this->app->instance(EntityRegistry::class, $registry);

        $tool = new EntityCountTool;
        $result = $tool('nonexistent');

        $this->assertIsString($result);
        $this->assertStringContains("Unknown entity type: 'nonexistent'.", $result);
    }

    public function test_counts_by_status_returns_string_when_no_status_entities(): void
    {
        $registry = Mockery::mock(EntityRegistry::class);
        $registry->shouldReceive('allTypes')->andReturn(new Collection([
            ['class' => 'App\\Models\\Task', 'table' => 'tasks', 'label' => 'Task'],
        ]));
        $registry->shouldReceive('countsByStatus')->once()->andReturn([]);

        $this->app->instance(EntityRegistry::class, $registry);

        $tool = new EntityCountTool;
        $result = $tool(null, true);

        $this->assertIsString($result);
        $this->assertSame('No entity types with a status column found.', $result);
    }

    public function test_counts_by_status_for_specific_entity(): void
    {
        $statusCounts = [
            'Task' => ['active' => 5, 'completed' => 3],
            'Project' => ['draft' => 2, 'published' => 7],
        ];

        $registry = Mockery::mock(EntityRegistry::class);
        $registry->shouldReceive('allTypes')->andReturn(new Collection([
            ['class' => 'App\\Models\\Task', 'table' => 'tasks', 'label' => 'Task'],
        ]));
        $registry->shouldReceive('countsByStatus')->once()->andReturn($statusCounts);

        $this->app->instance(EntityRegistry::class, $registry);

        $tool = new EntityCountTool;
        $result = $tool('Task', true);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('Task', $result);
        $this->assertSame(['active' => 5, 'completed' => 3], $result['Task']);
    }

    public function test_counts_by_status_for_unknown_entity(): void
    {
        $registry = Mockery::mock(EntityRegistry::class);
        $registry->shouldReceive('allTypes')->andReturn(new Collection([
            ['class' => 'App\\Models\\Task', 'table' => 'tasks', 'label' => 'Task'],
        ]));
        $registry->shouldReceive('countsByStatus')->once()->andReturn([
            'Task' => ['active' => 5],
        ]);

        $this->app->instance(EntityRegistry::class, $registry);

        $tool = new EntityCountTool;
        $result = $tool('nonexistent', true);

        $this->assertIsString($result);
        $this->assertStringContains("Entity type 'nonexistent' has no status column or doesn't exist.", $result);
    }

    public function test_counts_all_by_status(): void
    {
        $statusCounts = [
            'Task' => ['active' => 5, 'completed' => 3],
            'Project' => ['draft' => 2],
        ];

        $registry = Mockery::mock(EntityRegistry::class);
        $registry->shouldReceive('allTypes')->andReturn(new Collection([
            ['class' => 'App\\Models\\Task', 'table' => 'tasks', 'label' => 'Task'],
        ]));
        $registry->shouldReceive('countsByStatus')->once()->andReturn($statusCounts);

        $this->app->instance(EntityRegistry::class, $registry);

        $tool = new EntityCountTool;
        $result = $tool(null, true);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertArrayHasKey('Task', $result);
        $this->assertArrayHasKey('Project', $result);
    }

    /**
     * Helper to assert a string contains a substring (PHPUnit 11 removed assertStringContainsString short alias).
     */
    private static function assertStringContains(string $needle, string $haystack): void
    {
        self::assertStringContainsString($needle, $haystack);
    }
}
