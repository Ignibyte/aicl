<?php

declare(strict_types=1);

namespace Aicl\Tests\Unit\Http\Middleware;

use Aicl\Http\Middleware\NormalizeResponseMiddleware;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use PHPUnit\Framework\TestCase;

/**
 * Regression tests for NormalizeResponseMiddleware.
 *
 * This is a NEW file introduced during PHPStan migration to handle
 * Livewire Redirector objects that lack Symfony Response's $headers
 * property. Tests all three response normalization branches:
 * proper Response passthrough, Redirector-like object conversion,
 * and generic fallback.
 */
class NormalizeResponseMiddlewareRegressionTest extends TestCase
{
    private NormalizeResponseMiddleware $middleware;

    protected function setUp(): void
    {
        parent::setUp();

        $this->middleware = new NormalizeResponseMiddleware;
    }

    // -- Symfony Response passthrough --

    /**
     * Test proper Symfony Response objects pass through unchanged.
     *
     * When the next middleware returns a standard Symfony Response,
     * it should be returned as-is without wrapping.
     */
    public function test_passes_through_symfony_response(): void
    {
        // Arrange
        $request = Request::create('/test', 'GET');
        $expectedResponse = new Response('ok', 200);

        // Act
        $result = $this->middleware->handle($request, fn () => $expectedResponse);

        // Assert: exact same instance returned
        $this->assertSame($expectedResponse, $result);
    }

    /**
     * Test RedirectResponse passes through unchanged.
     *
     * RedirectResponse extends Symfony Response, so it should pass through.
     */
    public function test_passes_through_redirect_response(): void
    {
        // Arrange
        $request = Request::create('/test', 'GET');
        $redirect = new RedirectResponse('/dashboard');

        // Act
        $result = $this->middleware->handle($request, fn () => $redirect);

        // Assert: same redirect instance returned
        $this->assertSame($redirect, $result);
    }

    // -- Redirector-like object conversion --

    /**
     * Test objects with getTargetUrl() are converted to RedirectResponse.
     *
     * Simulates a Livewire Redirector that has getTargetUrl() but is not
     * a Symfony Response. The middleware should extract the URL and create
     * a proper RedirectResponse.
     */
    public function test_converts_redirector_like_object(): void
    {
        // Arrange
        $request = Request::create('/test', 'GET');
        $fakeRedirector = new class
        {
            public function getTargetUrl(): string
            {
                return '/admin/dashboard';
            }
        };

        // Act
        $result = $this->middleware->handle($request, fn () => $fakeRedirector);

        // Assert: converted to a proper RedirectResponse
        $this->assertInstanceOf(RedirectResponse::class, $result);
        $this->assertSame('/admin/dashboard', $result->getTargetUrl());
    }

    // -- Generic fallback --

    /**
     * Test non-Response objects without getTargetUrl() fall back to current URL redirect.
     *
     * When the next middleware returns something that is neither a Response
     * nor has getTargetUrl(), the middleware creates a redirect to the current URL.
     */
    public function test_generic_fallback_redirects_to_current_url(): void
    {
        // Arrange
        $request = Request::create('https://example.com/admin/test', 'GET');
        $unknownResponse = new \stdClass;

        // Act
        $result = $this->middleware->handle($request, fn () => $unknownResponse);

        // Assert: redirects to the current request URL
        $this->assertInstanceOf(RedirectResponse::class, $result);
        $this->assertSame('https://example.com/admin/test', $result->getTargetUrl());
    }

    /**
     * Test generic fallback with string response.
     *
     * Edge case: middleware receives a plain string (not a Response object).
     */
    public function test_generic_fallback_with_string_response(): void
    {
        // Arrange
        $request = Request::create('https://example.com/page', 'GET');

        // Act
        $result = $this->middleware->handle($request, fn () => 'just a string');

        // Assert: redirects to current URL
        $this->assertInstanceOf(RedirectResponse::class, $result);
        $this->assertSame('https://example.com/page', $result->getTargetUrl());
    }

    /**
     * Test generic fallback with null response.
     *
     * Edge case: middleware receives null from the next handler.
     */
    public function test_generic_fallback_with_null_response(): void
    {
        // Arrange
        $request = Request::create('https://example.com/page', 'GET');

        // Act
        $result = $this->middleware->handle($request, fn () => null);

        // Assert: redirects to current URL
        $this->assertInstanceOf(RedirectResponse::class, $result);
    }
}
