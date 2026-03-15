<?php

namespace Aicl\Tests\Feature\Filament;

use Aicl\Events\EntityCreated;
use Aicl\Events\EntityDeleted;
use Aicl\Events\EntityUpdated;
use Aicl\Filament\Pages\ApiTokens;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ApiTokensPageTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'Aicl\Database\Seeders\RoleSeeder']);
        $this->artisan('db:seed', ['--class' => 'Aicl\Database\Seeders\SettingsSeeder']);

        Event::fake([
            EntityCreated::class,
            EntityUpdated::class,
            EntityDeleted::class,
        ]);

        $this->superAdmin = User::factory()->create();
        $this->superAdmin->assignRole('super_admin');
    }

    public function test_api_tokens_page_loads_successfully(): void
    {
        $response = $this->actingAs($this->superAdmin)->get('/admin/api-tokens');

        $response->assertOk();
    }

    public function test_api_tokens_page_shows_title(): void
    {
        $response = $this->actingAs($this->superAdmin)->get('/admin/api-tokens');

        $response->assertOk();
        $response->assertSee('API', false);
    }

    public function test_mcp_tab_visible_when_feature_flag_enabled(): void
    {
        config(['aicl.features.mcp' => true]);

        $page = new ApiTokens;
        $this->assertTrue($page->isMcpAvailable());
    }

    public function test_mcp_tab_hidden_when_feature_flag_disabled(): void
    {
        config(['aicl.features.mcp' => false]);

        $page = new ApiTokens;
        $this->assertFalse($page->isMcpAvailable());
    }

    public function test_get_available_scopes_returns_expected_scopes(): void
    {
        $page = new ApiTokens;
        $scopes = $page->getAvailableScopes();

        $this->assertArrayHasKey('*', $scopes);
        $this->assertArrayHasKey('read', $scopes);
        $this->assertArrayHasKey('write', $scopes);
        $this->assertArrayHasKey('delete', $scopes);
        $this->assertArrayHasKey('mcp', $scopes);
        $this->assertArrayHasKey('transitions', $scopes);

        $this->assertSame('Full Access', $scopes['*']);
        $this->assertStringContainsString('Read', $scopes['read']);
        $this->assertStringContainsString('Write', $scopes['write']);
        $this->assertStringContainsString('Delete', $scopes['delete']);
        $this->assertStringContainsString('MCP', $scopes['mcp']);
    }

    public function test_get_scope_presets_returns_expected_presets(): void
    {
        $page = new ApiTokens;
        $presets = $page->getScopePresets();

        $this->assertArrayHasKey('Full Access', $presets);
        $this->assertArrayHasKey('Read Only', $presets);
        $this->assertArrayHasKey('MCP Client', $presets);
        $this->assertArrayHasKey('MCP Read Only', $presets);

        $this->assertSame(['*'], $presets['Full Access']);
        $this->assertSame(['read'], $presets['Read Only']);
        $this->assertContains('mcp', $presets['MCP Client']);
        $this->assertContains('read', $presets['MCP Client']);
        $this->assertContains('write', $presets['MCP Client']);
        $this->assertContains('mcp', $presets['MCP Read Only']);
        $this->assertContains('read', $presets['MCP Read Only']);
    }

    public function test_apply_scope_preset_updates_selected_scopes(): void
    {
        $page = new ApiTokens;
        $page->selectedScopes = ['*'];

        $page->applyScopePreset('Read Only');

        $this->assertSame(['read'], $page->selectedScopes);
    }

    public function test_apply_scope_preset_mcp_client(): void
    {
        $page = new ApiTokens;

        $page->applyScopePreset('MCP Client');

        $this->assertContains('mcp', $page->selectedScopes);
        $this->assertContains('read', $page->selectedScopes);
        $this->assertContains('write', $page->selectedScopes);
    }

    public function test_apply_invalid_scope_preset_does_not_change_scopes(): void
    {
        $page = new ApiTokens;
        $page->selectedScopes = ['*'];

        $page->applyScopePreset('NonExistent');

        $this->assertSame(['*'], $page->selectedScopes);
    }

    public function test_get_mcp_url_returns_correct_url(): void
    {
        config(['app.url' => 'https://example.com']);
        config(['aicl.mcp.path' => '/mcp']);

        $page = new ApiTokens;
        $url = $page->getMcpUrl();

        $this->assertSame('https://example.com/mcp', $url);
    }

    public function test_get_mcp_tool_count_returns_zero_when_disabled(): void
    {
        config(['aicl.features.mcp' => false]);

        $page = new ApiTokens;
        $page->mcpEnabled = false;

        $this->assertSame(0, $page->getMcpToolCount());
    }

    public function test_page_class_structure(): void
    {
        $page = new ApiTokens;

        $reflection = new \ReflectionProperty($page, 'view');
        $this->assertSame('aicl::filament.pages.api-tokens', $reflection->getValue($page));

        $this->assertSame('api-tokens', (new \ReflectionProperty($page, 'slug'))->getValue(null));
    }

    public function test_api_tokens_page_not_accessible_when_api_feature_disabled(): void
    {
        config(['aicl.features.api' => false]);

        $response = $this->actingAs($this->superAdmin)->get('/admin/api-tokens');

        $this->assertFilamentAccessDenied($response);
    }

    public function test_api_tokens_page_redirects_guest(): void
    {
        $response = $this->get('/admin/api-tokens');

        $response->assertRedirect();
    }

    public function test_clear_created_token_resets_value(): void
    {
        $page = new ApiTokens;
        $page->createdToken = 'some-token-value';

        $page->clearCreatedToken();

        $this->assertNull($page->createdToken);
    }

    public function test_default_active_tab_is_tokens(): void
    {
        $page = new ApiTokens;

        $this->assertSame('tokens', $page->activeTab);
    }

    public function test_default_selected_scopes_is_full_access(): void
    {
        $page = new ApiTokens;

        $this->assertSame(['*'], $page->selectedScopes);
    }
}
