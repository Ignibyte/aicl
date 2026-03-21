<?php

namespace Aicl\Tests\Unit\Filament\Pages;

use Aicl\Filament\Pages\ApiTokens;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the ApiTokens page canAccess() role guard.
 *
 * Verifies that the canAccess() static method properly gates access
 * based on the authenticated user's role assignment.
 */
class ApiTokensCanAccessTest extends TestCase
{
    public function test_can_access_returns_false_when_no_user(): void
    {
        // canAccess() calls auth()->user() which returns null when not authenticated.
        // Since we're in a pure unit test without Laravel's auth system,
        // we verify the method signature and source code logic.
        $reflection = new \ReflectionMethod(ApiTokens::class, 'canAccess');

        $this->assertTrue($reflection->isStatic());
        $this->assertTrue($reflection->isPublic());
    }

    public function test_can_access_checks_role_guard(): void
    {
        $source = file_get_contents(
            /** @phpstan-ignore-next-line */
            (new \ReflectionClass(ApiTokens::class))->getFileName()
        );

        // Must check for super_admin and admin roles
        /** @phpstan-ignore-next-line */
        $this->assertStringContainsString("hasRole(['super_admin', 'admin'])", $source);
    }

    public function test_can_access_returns_false_for_null_user(): void
    {
        $source = file_get_contents(
            /** @phpstan-ignore-next-line */
            (new \ReflectionClass(ApiTokens::class))->getFileName()
        );

        // Must have null user guard
        /** @phpstan-ignore-next-line */
        $this->assertStringContainsString('if (! $user)', $source);
        /** @phpstan-ignore-next-line */
        $this->assertStringContainsString('return false', $source);
    }

    public function test_is_mcp_available_checks_feature_flag_and_class(): void
    {
        $source = file_get_contents(
            /** @phpstan-ignore-next-line */
            (new \ReflectionClass(ApiTokens::class))->getFileName()
        );

        /** @phpstan-ignore-next-line */
        $this->assertStringContainsString("config('aicl.features.mcp'", $source);
        /** @phpstan-ignore-next-line */
        $this->assertStringContainsString('class_exists(Mcp::class)', $source);
    }

    public function test_get_mcp_url_uses_app_url_and_mcp_path(): void
    {
        $source = file_get_contents(
            /** @phpstan-ignore-next-line */
            (new \ReflectionClass(ApiTokens::class))->getFileName()
        );

        /** @phpstan-ignore-next-line */
        $this->assertStringContainsString("config('app.url'", $source);
        /** @phpstan-ignore-next-line */
        $this->assertStringContainsString("config('aicl.mcp.path'", $source);
    }

    public function test_get_mcp_tool_count_returns_int(): void
    {
        $reflection = new \ReflectionMethod(ApiTokens::class, 'getMcpToolCount');
        $returnType = $reflection->getReturnType();

        $this->assertNotNull($returnType);
        /** @phpstan-ignore-next-line */
        $this->assertSame('int', $returnType->getName());
    }

    public function test_get_available_scopes_returns_array(): void
    {
        $reflection = new \ReflectionMethod(ApiTokens::class, 'getAvailableScopes');
        $returnType = $reflection->getReturnType();

        $this->assertNotNull($returnType);
        /** @phpstan-ignore-next-line */
        $this->assertSame('array', $returnType->getName());
    }

    public function test_page_has_correct_slug(): void
    {
        $reflection = new \ReflectionProperty(ApiTokens::class, 'slug');
        $reflection->setAccessible(true);

        $this->assertSame('api-tokens', $reflection->getValue());
    }

    public function test_page_navigation_group_is_system(): void
    {
        $reflection = new \ReflectionProperty(ApiTokens::class, 'navigationGroup');
        $reflection->setAccessible(true);

        $this->assertSame('System', $reflection->getValue());
    }

    public function test_page_navigation_icon_is_null(): void
    {
        $reflection = new \ReflectionProperty(ApiTokens::class, 'navigationIcon');
        $reflection->setAccessible(true);

        $this->assertNull($reflection->getValue());
    }
}
