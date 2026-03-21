<?php

declare(strict_types=1);

namespace Aicl\Tests\Unit\Filament\Pages;

use Aicl\Filament\Pages\ApiTokens;
use App\Models\User;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Regression tests for ApiTokens page PHPStan changes.
 *
 * Covers the new canAccess() authorization check (null guard on auth()->user()),
 * scope presets, MCP availability detection, and null guards on getTokens(),
 * createToken(), and revokeToken() methods.
 */
class ApiTokensRegressionTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed roles so hasRole() checks work correctly
        $this->artisan('db:seed', ['--class' => 'Aicl\Database\Seeders\RoleSeeder']);
    }

    // -- canAccess null guard --

    /**
     * Test canAccess returns false when no user is authenticated.
     *
     * PHPStan change: Added null guard on auth()->user() before hasRole().
     * Previously would throw on null user.
     */
    public function test_can_access_returns_false_when_unauthenticated(): void
    {
        // Act: call canAccess without authentication
        $result = ApiTokens::canAccess();

        // Assert: unauthenticated users cannot access the page
        $this->assertFalse($result);
    }

    /**
     * Test canAccess returns true for admin users.
     *
     * Happy path: authenticated admin user has access.
     */
    public function test_can_access_returns_true_for_admin(): void
    {
        // Arrange: create and authenticate an admin user
        /** @var User $admin */
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin);

        // Act
        $result = ApiTokens::canAccess();

        // Assert: admin can access
        $this->assertTrue($result);
    }

    /**
     * Test canAccess returns true for super_admin users.
     *
     * Happy path: authenticated super_admin user has access.
     */
    public function test_can_access_returns_true_for_super_admin(): void
    {
        // Arrange: create and authenticate a super_admin user
        /** @var User $superAdmin */
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');
        $this->actingAs($superAdmin);

        // Act
        $result = ApiTokens::canAccess();

        // Assert: super_admin can access
        $this->assertTrue($result);
    }

    /**
     * Test canAccess returns false for regular users without admin roles.
     *
     * Edge case: authenticated user without the required roles.
     */
    public function test_can_access_returns_false_for_regular_user(): void
    {
        // Arrange: create and authenticate a user without admin role
        /** @var User $user */
        $user = User::factory()->create();
        $this->actingAs($user);

        // Act
        $result = ApiTokens::canAccess();

        // Assert: regular user cannot access
        $this->assertFalse($result);
    }

    // -- Scope presets --

    /**
     * Test getScopePresets returns expected preset configurations.
     *
     * PHPStan change: Added typed property and return type annotations.
     * Verifies preset structure and expected scope assignments.
     */
    public function test_get_scope_presets_returns_expected_presets(): void
    {
        // Arrange
        $page = new ApiTokens;

        // Act
        $presets = $page->getScopePresets();

        // Assert: all four presets are present with correct scopes
        $this->assertArrayHasKey('Full Access', $presets);
        $this->assertArrayHasKey('Read Only', $presets);
        $this->assertArrayHasKey('MCP Client', $presets);
        $this->assertArrayHasKey('MCP Read Only', $presets);
        $this->assertSame(['*'], $presets['Full Access']);
        $this->assertSame(['read'], $presets['Read Only']);
        $this->assertContains('mcp', $presets['MCP Client']);
        $this->assertContains('read', $presets['MCP Read Only']);
    }

    /**
     * Test applyScopePreset sets selected scopes to preset values.
     *
     * Verifies the preset application mutates the selectedScopes property.
     */
    public function test_apply_scope_preset_sets_selected_scopes(): void
    {
        // Arrange
        $page = new ApiTokens;
        $page->selectedScopes = ['*']; // default

        // Act: apply the Read Only preset
        $page->applyScopePreset('Read Only');

        // Assert: selectedScopes updated to Read Only preset
        $this->assertSame(['read'], $page->selectedScopes);
    }

    /**
     * Test applyScopePreset ignores unknown preset names.
     *
     * Edge case: passing a preset name that doesn't exist should not change scopes.
     */
    public function test_apply_scope_preset_ignores_unknown_preset(): void
    {
        // Arrange
        $page = new ApiTokens;
        $page->selectedScopes = ['*'];

        // Act: apply a non-existent preset
        $page->applyScopePreset('Nonexistent Preset');

        // Assert: selectedScopes unchanged
        $this->assertSame(['*'], $page->selectedScopes);
    }

    // -- Available scopes --

    /**
     * Test getAvailableScopes returns all expected OAuth scope definitions.
     *
     * PHPStan change: Added @return type annotation for scope map.
     */
    public function test_get_available_scopes_contains_expected_scopes(): void
    {
        // Arrange
        $page = new ApiTokens;

        // Act
        $scopes = $page->getAvailableScopes();

        // Assert: all required scope keys are present
        $this->assertArrayHasKey('*', $scopes);
        $this->assertArrayHasKey('read', $scopes);
        $this->assertArrayHasKey('write', $scopes);
        $this->assertArrayHasKey('delete', $scopes);
        $this->assertArrayHasKey('mcp', $scopes);
        $this->assertArrayHasKey('transitions', $scopes);
        $this->assertCount(6, $scopes);
    }

    // -- MCP availability --

    /**
     * Test isMcpAvailable returns false when MCP feature is disabled.
     *
     * PHPStan change: New method added during refactor.
     */
    public function test_is_mcp_available_returns_false_when_disabled(): void
    {
        // Arrange: disable MCP feature
        config(['aicl.features.mcp' => false]);
        $page = new ApiTokens;

        // Act
        $result = $page->isMcpAvailable();

        // Assert: MCP not available when feature flag is off
        $this->assertFalse($result);
    }

    // -- MCP URL construction --

    /**
     * Test getMcpUrl constructs URL from app.url and mcp.path config.
     *
     * Verifies the URL combines app URL with MCP path correctly.
     */
    public function test_get_mcp_url_constructs_correct_url(): void
    {
        // Arrange: set config values
        config([
            'app.url' => 'https://example.com',
            'aicl.mcp.path' => '/mcp',
        ]);
        $page = new ApiTokens;

        // Act
        $url = $page->getMcpUrl();

        // Assert: URL is correctly constructed
        $this->assertSame('https://example.com/mcp', $url);
    }

    /**
     * Test getMcpUrl handles trailing slash on app.url.
     *
     * Edge case: app.url with trailing slash should not produce double slash.
     */
    public function test_get_mcp_url_strips_trailing_slash(): void
    {
        // Arrange: app.url has trailing slash
        config([
            'app.url' => 'https://example.com/',
            'aicl.mcp.path' => '/mcp',
        ]);
        $page = new ApiTokens;

        // Act
        $url = $page->getMcpUrl();

        // Assert: no double slash in URL
        $this->assertSame('https://example.com/mcp', $url);
    }

    // -- Token retrieval null guard --

    /**
     * Test getTokens returns empty array when no user is authenticated.
     *
     * PHPStan change: Added null guard on Auth::user() returning early with [].
     */
    public function test_get_tokens_returns_empty_array_when_unauthenticated(): void
    {
        // Arrange
        $page = new ApiTokens;

        // Act: call without authentication
        $tokens = $page->getTokens();

        // Assert: empty array returned, no exception
        $this->assertSame([], $tokens);
    }

    // -- Clear created token --

    /**
     * Test clearCreatedToken sets createdToken to null.
     *
     * Verifies the token display clearing mechanism works.
     */
    public function test_clear_created_token_sets_null(): void
    {
        // Arrange
        $page = new ApiTokens;
        $page->createdToken = 'some-token-value';

        // Act
        $page->clearCreatedToken();

        // Assert
        $this->assertNull($page->createdToken);
    }

    // -- Page title and navigation --

    /**
     * Test getTitle returns the updated "API & Integrations" title.
     *
     * PHPStan change: title was changed from "API Tokens" to "API & Integrations".
     */
    public function test_get_title_returns_api_and_integrations(): void
    {
        // Arrange
        $page = new ApiTokens;

        // Act
        $title = $page->getTitle();

        // Assert: reflects the updated title
        // getTitle returns string|Htmlable -- cast safely for comparison
        $titleStr = $title instanceof Htmlable ? $title->toHtml() : $title;
        $this->assertSame('API & Integrations', $titleStr);
    }

    /**
     * Test getNavigationLabel returns the updated label.
     */
    public function test_get_navigation_label_returns_api_and_integrations(): void
    {
        // Act
        $label = ApiTokens::getNavigationLabel();

        // Assert
        $this->assertSame('API & Integrations', $label);
    }

    // -- getMcpToolCount --

    /**
     * Test getMcpToolCount returns 0 when MCP is not available.
     *
     * Edge case: MCP feature disabled should return 0.
     */
    public function test_get_mcp_tool_count_returns_zero_when_mcp_unavailable(): void
    {
        // Arrange: disable MCP
        config(['aicl.features.mcp' => false]);
        $page = new ApiTokens;

        // Act
        $count = $page->getMcpToolCount();

        // Assert: zero tools when MCP disabled
        $this->assertSame(0, $count);
    }
}
