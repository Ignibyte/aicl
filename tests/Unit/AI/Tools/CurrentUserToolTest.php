<?php

namespace Aicl\Tests\Unit\AI\Tools;

use Aicl\AI\Contracts\AiTool;
use Aicl\AI\Tools\BaseTool;
use Aicl\AI\Tools\CurrentUserTool;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurrentUserToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_implements_ai_tool_interface(): void
    {
        $tool = new CurrentUserTool;

        $this->assertInstanceOf(AiTool::class, $tool);
        $this->assertInstanceOf(BaseTool::class, $tool);
    }

    public function test_has_correct_name(): void
    {
        $tool = new CurrentUserTool;

        $this->assertSame('current_user', $tool->getName());
    }

    public function test_category_is_system(): void
    {
        $tool = new CurrentUserTool;

        $this->assertSame('system', $tool->category());
    }

    public function test_requires_auth(): void
    {
        $tool = new CurrentUserTool;

        $this->assertTrue($tool->requiresAuth());
    }

    public function test_returns_string_when_no_authenticated_user(): void
    {
        $tool = new CurrentUserTool;

        $result = $tool();

        $this->assertIsString($result);
        $this->assertSame('No authenticated user context available.', $result);
    }

    public function test_returns_string_when_user_not_found(): void
    {
        $tool = new CurrentUserTool;
        $tool->setAuthenticatedUser(99999);

        $result = $tool();

        $this->assertIsString($result);
        $this->assertSame('User not found.', $result);
    }

    public function test_returns_user_info_array_for_valid_user(): void
    {
        $this->artisan('db:seed', ['--class' => 'Aicl\Database\Seeders\RoleSeeder']);

        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
        $user->assignRole('super_admin');

        $tool = new CurrentUserTool;
        $tool->setAuthenticatedUser($user->id);

        $result = $tool();

        $this->assertIsArray($result);
        $this->assertSame($user->id, $result['id']);
        $this->assertSame('Test User', $result['name']);
        $this->assertSame('test@example.com', $result['email']);
        $this->assertIsArray($result['roles']);
        $this->assertContains('super_admin', $result['roles']);
        $this->assertArrayHasKey('created_at', $result);
    }
}
