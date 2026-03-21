<?php

namespace Aicl\Tests\Unit\AI\Tools;

use Aicl\AI\Contracts\AiTool;
use Aicl\AI\Tools\BaseTool;
use Aicl\AI\Tools\QueryEntityTool;
use Aicl\Services\EntityRegistry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Mockery;
use Tests\TestCase;

class QueryEntityToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_implements_ai_tool_interface(): void
    {
        $tool = new QueryEntityTool;

        $this->assertInstanceOf(AiTool::class, $tool);
        $this->assertInstanceOf(BaseTool::class, $tool);
    }

    public function test_has_correct_name(): void
    {
        $tool = new QueryEntityTool;

        $this->assertSame('query_entity', $tool->getName());
    }

    public function test_category_is_queries(): void
    {
        $tool = new QueryEntityTool;

        $this->assertSame('queries', $tool->category());
    }

    public function test_requires_auth(): void
    {
        $tool = new QueryEntityTool;

        $this->assertTrue($tool->requiresAuth());
    }

    public function test_returns_error_for_unknown_entity_type(): void
    {
        $registry = Mockery::mock(EntityRegistry::class);
        /** @phpstan-ignore-next-line */
        $registry->shouldReceive('allTypes')->andReturn(new Collection([
            ['class' => User::class, 'table' => 'users', 'label' => 'User'],
        ]));

        $this->app->instance(EntityRegistry::class, $registry);

        $tool = new QueryEntityTool;
        $result = $tool('nonexistent');

        $this->assertIsString($result);
        $this->assertStringContainsString("Unknown entity type: 'nonexistent'", $result);
        $this->assertStringContainsString('users', $result);
    }

    public function test_resolves_entity_by_table_name(): void
    {
        User::factory()->create(['name' => 'Test User']);

        $registry = Mockery::mock(EntityRegistry::class);
        /** @phpstan-ignore-next-line */
        $registry->shouldReceive('allTypes')->andReturn(new Collection([
            ['class' => User::class, 'table' => 'users', 'label' => 'User'],
        ]));

        $this->app->instance(EntityRegistry::class, $registry);

        Gate::shouldReceive('forUser')->andReturnSelf();
        Gate::shouldReceive('allows')->andReturn(true);

        $tool = new QueryEntityTool;
        $tool->setAuthenticatedUser(1);
        $result = $tool('users');

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
    }

    public function test_resolves_entity_by_label(): void
    {
        User::factory()->create();

        $registry = Mockery::mock(EntityRegistry::class);
        /** @phpstan-ignore-next-line */
        $registry->shouldReceive('allTypes')->andReturn(new Collection([
            ['class' => User::class, 'table' => 'users', 'label' => 'User'],
        ]));

        $this->app->instance(EntityRegistry::class, $registry);

        Gate::shouldReceive('forUser')->andReturnSelf();
        Gate::shouldReceive('allows')->andReturn(true);

        $tool = new QueryEntityTool;
        $tool->setAuthenticatedUser(1);
        $result = $tool('User');

        $this->assertIsArray($result);
        $this->assertGreaterThanOrEqual(1, count($result));
    }

    public function test_resolves_entity_by_class_basename(): void
    {
        User::factory()->create();

        $registry = Mockery::mock(EntityRegistry::class);
        /** @phpstan-ignore-next-line */
        $registry->shouldReceive('allTypes')->andReturn(new Collection([
            ['class' => User::class, 'table' => 'users', 'label' => 'User'],
        ]));

        $this->app->instance(EntityRegistry::class, $registry);

        Gate::shouldReceive('forUser')->andReturnSelf();
        Gate::shouldReceive('allows')->andReturn(true);

        $tool = new QueryEntityTool;
        $tool->setAuthenticatedUser(1);
        $result = $tool('user');

        $this->assertIsArray($result);
        $this->assertGreaterThanOrEqual(1, count($result));
    }

    public function test_returns_permission_denied_message(): void
    {
        $user = User::factory()->create();

        $registry = Mockery::mock(EntityRegistry::class);
        /** @phpstan-ignore-next-line */
        $registry->shouldReceive('allTypes')->andReturn(new Collection([
            ['class' => User::class, 'table' => 'users', 'label' => 'User'],
        ]));

        $this->app->instance(EntityRegistry::class, $registry);

        Gate::shouldReceive('forUser')->andReturnSelf();
        Gate::shouldReceive('allows')->with('viewAny', User::class)->andReturn(false);

        $tool = new QueryEntityTool;
        $tool->setAuthenticatedUser($user->id);
        $result = $tool('users');

        $this->assertIsString($result);
        $this->assertStringContainsString('do not have permission', $result);
    }

    public function test_respects_limit_parameter(): void
    {
        User::factory()->count(5)->create();

        $registry = Mockery::mock(EntityRegistry::class);
        /** @phpstan-ignore-next-line */
        $registry->shouldReceive('allTypes')->andReturn(new Collection([
            ['class' => User::class, 'table' => 'users', 'label' => 'User'],
        ]));

        $this->app->instance(EntityRegistry::class, $registry);

        Gate::shouldReceive('forUser')->andReturnSelf();
        Gate::shouldReceive('allows')->andReturn(true);

        $tool = new QueryEntityTool;
        $tool->setAuthenticatedUser(1);
        $result = $tool('users', null, 2);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
    }

    public function test_limit_capped_at_50(): void
    {
        $registry = Mockery::mock(EntityRegistry::class);
        /** @phpstan-ignore-next-line */
        $registry->shouldReceive('allTypes')->andReturn(new Collection([
            ['class' => User::class, 'table' => 'users', 'label' => 'User'],
        ]));

        $this->app->instance(EntityRegistry::class, $registry);

        Gate::shouldReceive('forUser')->andReturnSelf();
        Gate::shouldReceive('allows')->andReturn(true);

        // Create a few users to verify the query runs
        User::factory()->create();

        $tool = new QueryEntityTool;
        $tool->setAuthenticatedUser(1);

        // Should not error with limit > 50 — it's capped internally
        $result = $tool('users', null, 100);

        $this->assertIsArray($result);
    }

    public function test_returns_no_records_message_when_empty(): void
    {
        // Ensure no users exist
        User::query()->delete();

        $registry = Mockery::mock(EntityRegistry::class);
        /** @phpstan-ignore-next-line */
        $registry->shouldReceive('allTypes')->andReturn(new Collection([
            ['class' => User::class, 'table' => 'users', 'label' => 'User'],
        ]));

        $this->app->instance(EntityRegistry::class, $registry);

        Gate::shouldReceive('forUser')->andReturnSelf();
        Gate::shouldReceive('allows')->andReturn(true);

        $tool = new QueryEntityTool;
        $tool->setAuthenticatedUser(1);
        $result = $tool('users');

        $this->assertIsString($result);
        $this->assertStringContainsString('No User records found', $result);
    }

    public function test_applies_filters(): void
    {
        User::factory()->create(['name' => 'Alice']);
        User::factory()->create(['name' => 'Bob']);

        $registry = Mockery::mock(EntityRegistry::class);
        /** @phpstan-ignore-next-line */
        $registry->shouldReceive('allTypes')->andReturn(new Collection([
            ['class' => User::class, 'table' => 'users', 'label' => 'User'],
        ]));

        $this->app->instance(EntityRegistry::class, $registry);

        Gate::shouldReceive('forUser')->andReturnSelf();
        Gate::shouldReceive('allows')->andReturn(true);

        $tool = new QueryEntityTool;
        $tool->setAuthenticatedUser(1);
        $result = $tool('users', 'name:Alice');

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertSame('Alice', $result[0]['name']);
    }

    public function test_skips_malformed_filter_pairs(): void
    {
        User::factory()->create(['name' => 'Alice']);

        $registry = Mockery::mock(EntityRegistry::class);
        /** @phpstan-ignore-next-line */
        $registry->shouldReceive('allTypes')->andReturn(new Collection([
            ['class' => User::class, 'table' => 'users', 'label' => 'User'],
        ]));

        $this->app->instance(EntityRegistry::class, $registry);

        Gate::shouldReceive('forUser')->andReturnSelf();
        Gate::shouldReceive('allows')->andReturn(true);

        $tool = new QueryEntityTool;
        $tool->setAuthenticatedUser(1);

        // "badfilter" has no colon — should be skipped, returning all results
        $result = $tool('users', 'badfilter,name:Alice');

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertSame('Alice', $result[0]['name']);
    }
}
