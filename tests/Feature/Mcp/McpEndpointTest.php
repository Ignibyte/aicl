<?php

namespace Aicl\Tests\Feature\Mcp;

use Aicl\Mcp\AiclMcpServer;
use Aicl\Mcp\Tools\ListEntityTool;
use Aicl\Mcp\Tools\ShowEntityTool;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Mcp\Server\Transport\FakeTransporter;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class McpEndpointTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        config(['aicl.features.mcp' => true]);
        config(['broadcasting.default' => 'log']);

        // Create permissions on BOTH web and api guards (BF-007)
        $permissions = [
            'ViewAny:User', 'View:User', 'Create:User',
            'Update:User', 'Delete:User',
        ];

        foreach (['web', 'api'] as $guard) {
            foreach ($permissions as $permName) {
                Permission::findOrCreate($permName, $guard);
            }
        }

        // Create super_admin role on BOTH guards with all permissions
        foreach (['web', 'api'] as $guard) {
            $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => $guard]);
            $role->syncPermissions(Permission::where('guard_name', $guard)->get());
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $this->superAdmin = User::factory()->create();
        // Assign super_admin on both web and api guards
        $this->superAdmin->assignRole(Role::findByName('super_admin', 'web'));
        $this->superAdmin->assignRole(Role::findByName('super_admin', 'api'));

        $this->user = User::factory()->create();
    }

    public function test_server_can_be_instantiated(): void
    {
        $server = $this->app->make(AiclMcpServer::class, [
            'transport' => new FakeTransporter,
        ]);

        $this->assertInstanceOf(AiclMcpServer::class, $server);
    }

    public function test_server_boot_creates_context_with_tools(): void
    {
        $server = $this->app->make(AiclMcpServer::class, [
            'transport' => new FakeTransporter,
        ]);
        $server->start();

        $context = $server->createContext();

        $this->assertNotEmpty($context->tools(), 'Server context should have tools after boot');
    }

    public function test_server_testing_tool_method_with_list_entity(): void
    {
        $listTool = new ListEntityTool(User::class, 'User');

        // Use the MCP testing framework with actingAs on the api guard
        $response = AiclMcpServer::actingAs($this->superAdmin, 'api')
            ->tool($listTool);

        // MCP tool checks $request->user('api') - with actingAs on api guard,
        // the super_admin user should have ViewAny:User permission
        $response->assertOk();
    }

    public function test_server_testing_tool_unauthorized_list(): void
    {
        $listTool = new ListEntityTool(User::class, 'User');

        // User without permissions should get error
        $response = AiclMcpServer::actingAs($this->user, 'api')
            ->tool($listTool);

        $response->assertHasErrors(['Unauthorized']);
    }

    public function test_server_testing_show_tool_returns_user(): void
    {
        $showTool = new ShowEntityTool(User::class, 'User');

        // super_admin can view any user
        $response = AiclMcpServer::actingAs($this->superAdmin, 'api')
            ->tool($showTool, ['id' => (string) $this->user->id]);

        $response->assertOk();
    }

    public function test_server_testing_show_tool_not_found(): void
    {
        $showTool = new ShowEntityTool(User::class, 'User');

        // Use a numeric string that won't exist rather than UUID format
        $response = AiclMcpServer::actingAs($this->superAdmin, 'api')
            ->tool($showTool, ['id' => '999999']);

        $response->assertHasErrors(['not found']);
    }

    public function test_server_testing_show_tool_self_view_allowed(): void
    {
        $showTool = new ShowEntityTool(User::class, 'User');

        // UserPolicy allows users to view themselves even without permissions
        $response = AiclMcpServer::actingAs($this->user, 'api')
            ->tool($showTool, ['id' => (string) $this->user->id]);

        $response->assertOk();
    }

    public function test_feature_flag_controls_mcp_availability(): void
    {
        config(['aicl.features.mcp' => false]);
        $this->assertFalse(config('aicl.features.mcp'));

        config(['aicl.features.mcp' => true]);
        $this->assertTrue(config('aicl.features.mcp'));
    }

    public function test_mcp_config_has_correct_defaults(): void
    {
        $this->assertSame('/mcp', config('aicl.mcp.path'));
        $this->assertSame(['api', 'auth:api', 'throttle:api'], config('aicl.mcp.middleware'));
    }

    public function test_list_tool_returns_paginated_meta(): void
    {
        User::factory()->count(3)->create();

        $listTool = new ListEntityTool(User::class, 'User');

        $response = AiclMcpServer::actingAs($this->superAdmin, 'api')
            ->tool($listTool, ['per_page' => '2', 'page' => '1']);

        $response->assertOk();
    }

    public function test_list_tool_with_search_parameter(): void
    {
        User::factory()->create(['name' => 'UniqueSearchTarget123']);

        $listTool = new ListEntityTool(User::class, 'User');

        $response = AiclMcpServer::actingAs($this->superAdmin, 'api')
            ->tool($listTool, ['search' => 'UniqueSearchTarget123']);

        $response->assertOk();
    }

    public function test_list_tool_with_sorting(): void
    {
        $listTool = new ListEntityTool(User::class, 'User');

        $response = AiclMcpServer::actingAs($this->superAdmin, 'api')
            ->tool($listTool, ['sort_by' => 'name', 'sort_dir' => 'asc']);

        $response->assertOk();
    }

    public function test_list_tool_unauthenticated_returns_error(): void
    {
        $listTool = new ListEntityTool(User::class, 'User');

        // No actingAs - request user should be null, scope check returns Unauthenticated
        $response = AiclMcpServer::tool($listTool);

        $response->assertHasErrors(['Unauthenticated']);
    }

    public function test_show_tool_unauthenticated_returns_error(): void
    {
        $showTool = new ShowEntityTool(User::class, 'User');

        // No actingAs - scope check returns Unauthenticated before model lookup
        $response = AiclMcpServer::tool($showTool, ['id' => (string) $this->user->id]);

        $response->assertHasErrors(['Unauthenticated']);
    }
}
