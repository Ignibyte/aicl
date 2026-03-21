<?php

declare(strict_types=1);

namespace Aicl\Tests\Unit\Mcp\Concerns;

use Aicl\Mcp\Concerns\ChecksTokenScope;
use Illuminate\Contracts\Auth\Authenticatable;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use PHPUnit\Framework\TestCase;

/**
 * Regression tests for ChecksTokenScope trait PHPStan changes.
 *
 * Covers the null user guard (returns error when unauthenticated),
 * the token scope check (returns error when scope missing), and
 * the success path (returns null when authorized).
 */
class ChecksTokenScopeRegressionTest extends TestCase
{
    // -- Unauthenticated user --

    /**
     * Test checkScope returns error when user is not authenticated.
     *
     * The trait method calls $request->user('api') and returns an error
     * Response when null is returned.
     */
    public function test_check_scope_returns_error_when_unauthenticated(): void
    {
        // Arrange: create a concrete class that uses the trait
        $checker = new class
        {
            use ChecksTokenScope {
                checkScope as public;
            }
        };

        // Create a mock Request that returns null for user
        $request = \Mockery::mock(Request::class);
        $request->shouldReceive('user')->with('api')->andReturn(null); // @phpstan-ignore method.notFound

        // Act
        $result = $checker->checkScope($request, 'read'); // @phpstan-ignore argument.type
        // Assert: returns a Response error for unauthenticated
        $this->assertInstanceOf(Response::class, $result);
    }

    // -- Token with insufficient scope --

    /**
     * Test checkScope returns error when token lacks required scope.
     *
     * When the user has a token but it lacks the requested scope,
     * an error Response is returned.
     */
    public function test_check_scope_returns_error_for_missing_scope(): void
    {
        // Arrange
        $checker = new class
        {
            use ChecksTokenScope {
                checkScope as public;
            }
        };

        // Mock token that lacks 'write' scope
        $token = \Mockery::mock();
        $token->shouldReceive('can')->with('write')->andReturn(false); // @phpstan-ignore method.notFound

        // Mock user (must implement Authenticatable for Request::user() return type)
        $user = \Mockery::mock(Authenticatable::class);
        $user->shouldReceive('token')->andReturn($token); // @phpstan-ignore method.notFound

        $request = \Mockery::mock(Request::class);
        $request->shouldReceive('user')->with('api')->andReturn($user); // @phpstan-ignore method.notFound

        // Act
        $result = $checker->checkScope($request, 'write'); // @phpstan-ignore argument.type
        // Assert: returns error for missing scope
        $this->assertInstanceOf(Response::class, $result);
    }

    // -- Token with required scope --

    /**
     * Test checkScope returns null when token has the required scope.
     *
     * Happy path: authorized user with correct scope passes through.
     */
    public function test_check_scope_returns_null_when_authorized(): void
    {
        // Arrange
        $checker = new class
        {
            use ChecksTokenScope {
                checkScope as public;
            }
        };

        // Mock token with the required scope
        $token = \Mockery::mock();
        $token->shouldReceive('can')->with('read')->andReturn(true); // @phpstan-ignore method.notFound

        // Mock user (must implement Authenticatable)
        $user = \Mockery::mock(Authenticatable::class);
        $user->shouldReceive('token')->andReturn($token); // @phpstan-ignore method.notFound

        $request = \Mockery::mock(Request::class);
        $request->shouldReceive('user')->with('api')->andReturn($user); // @phpstan-ignore method.notFound

        // Act
        $result = $checker->checkScope($request, 'read'); // @phpstan-ignore argument.type
        // Assert: null means authorized (no error)
        $this->assertNull($result);
    }

    // -- User without token --

    /**
     * Test checkScope returns null when user has no token.
     *
     * Edge case: session-based user without a Passport token. The method
     * should pass through (return null) since there's no token to restrict.
     */
    public function test_check_scope_passes_when_no_token(): void
    {
        // Arrange
        $checker = new class
        {
            use ChecksTokenScope {
                checkScope as public;
            }
        };

        // Mock user with no token (returns null)
        $user = \Mockery::mock(Authenticatable::class);
        $user->shouldReceive('token')->andReturn(null); // @phpstan-ignore method.notFound

        $request = \Mockery::mock(Request::class);
        $request->shouldReceive('user')->with('api')->andReturn($user); // @phpstan-ignore method.notFound

        // Act
        $result = $checker->checkScope($request, 'read'); // @phpstan-ignore argument.type
        // Assert: null means authorized (no token restriction)
        $this->assertNull($result);
    }

    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }
}
