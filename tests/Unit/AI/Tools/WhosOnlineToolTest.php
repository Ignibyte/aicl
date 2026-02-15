<?php

namespace Aicl\Tests\Unit\AI\Tools;

use Aicl\AI\Contracts\AiTool;
use Aicl\AI\Tools\BaseTool;
use Aicl\AI\Tools\WhosOnlineTool;
use Aicl\Services\PresenceRegistry;
use Illuminate\Support\Collection;
use Mockery;
use Tests\TestCase;

class WhosOnlineToolTest extends TestCase
{
    public function test_implements_ai_tool_interface(): void
    {
        $tool = new WhosOnlineTool;

        $this->assertInstanceOf(AiTool::class, $tool);
        $this->assertInstanceOf(BaseTool::class, $tool);
    }

    public function test_has_correct_name(): void
    {
        $tool = new WhosOnlineTool;

        $this->assertSame('whos_online', $tool->getName());
    }

    public function test_category_is_system(): void
    {
        $tool = new WhosOnlineTool;

        $this->assertSame('system', $tool->category());
    }

    public function test_does_not_require_auth(): void
    {
        $tool = new WhosOnlineTool;

        $this->assertFalse($tool->requiresAuth());
    }

    public function test_returns_string_when_no_sessions(): void
    {
        $registry = Mockery::mock(PresenceRegistry::class);
        $registry->shouldReceive('allSessions')->once()->andReturn(collect());

        $this->app->instance(PresenceRegistry::class, $registry);

        $tool = new WhosOnlineTool;
        $result = $tool();

        $this->assertIsString($result);
        $this->assertSame('No users are currently online.', $result);
    }

    public function test_returns_array_with_session_data_when_users_online(): void
    {
        $sessions = new Collection([
            [
                'user_name' => 'Alice',
                'role' => 'admin',
                'last_seen_at' => '2026-02-14T10:00:00+00:00',
                'ip_address' => '192.168.1.1',
            ],
            [
                'user_name' => 'Bob',
                'role' => 'editor',
                'last_seen_at' => '2026-02-14T09:55:00+00:00',
                'ip_address' => '10.0.0.5',
            ],
        ]);

        $registry = Mockery::mock(PresenceRegistry::class);
        $registry->shouldReceive('allSessions')->once()->andReturn($sessions);

        $this->app->instance(PresenceRegistry::class, $registry);

        $tool = new WhosOnlineTool;
        $result = $tool();

        $this->assertIsArray($result);
        $this->assertCount(2, $result);

        $this->assertSame('Alice', $result[0]['user']);
        $this->assertSame('admin', $result[0]['role']);
        $this->assertSame('2026-02-14T10:00:00+00:00', $result[0]['last_seen']);
        $this->assertSame('192.168.1.1', $result[0]['ip']);

        $this->assertSame('Bob', $result[1]['user']);
        $this->assertSame('editor', $result[1]['role']);
    }

    public function test_handles_missing_session_keys_gracefully(): void
    {
        $sessions = new Collection([
            [
                'last_seen_at' => '2026-02-14T10:00:00+00:00',
            ],
        ]);

        $registry = Mockery::mock(PresenceRegistry::class);
        $registry->shouldReceive('allSessions')->once()->andReturn($sessions);

        $this->app->instance(PresenceRegistry::class, $registry);

        $tool = new WhosOnlineTool;
        $result = $tool();

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertSame('Unknown', $result[0]['user']);
        $this->assertNull($result[0]['role']);
        $this->assertNull($result[0]['ip']);
    }
}
