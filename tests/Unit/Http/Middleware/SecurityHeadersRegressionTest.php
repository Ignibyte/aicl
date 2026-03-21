<?php

declare(strict_types=1);

namespace Aicl\Tests\Unit\Http\Middleware;

use Aicl\Http\Middleware\SecurityHeadersMiddleware;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Tests\TestCase;

/**
 * Regression tests for SecurityHeadersMiddleware PHPStan changes.
 *
 * Covers the static config caching (per-worker state for Octane),
 * the resetCache() method, the non-Response passthrough guard,
 * the CSP string caching, and the isFilamentRequest() path detection.
 */
class SecurityHeadersRegressionTest extends TestCase
{
    private SecurityHeadersMiddleware $middleware;

    protected function setUp(): void
    {
        parent::setUp();

        // Reset cached static state between tests to prevent leaks
        SecurityHeadersMiddleware::resetCache();
        $this->middleware = new SecurityHeadersMiddleware;
    }

    protected function tearDown(): void
    {
        // Clean up static state after each test
        SecurityHeadersMiddleware::resetCache();
        parent::tearDown();
    }

    // -- Non-Response passthrough --

    /**
     * Test middleware passes through non-Response objects without headers.
     *
     * PHPStan change: Added `if (! $response instanceof Response)` guard
     * to handle Livewire Redirectors that lack the $headers property.
     */
    public function test_passes_through_non_response_objects(): void
    {
        // Arrange
        config(['aicl.security.headers.enabled' => true]);
        $request = Request::create('/admin/test', 'GET');
        $fakeRedirector = new \stdClass;

        // Act: middleware receives a non-Response object
        $result = $this->middleware->handle($request, fn () => $fakeRedirector);

        // Assert: returns the same object without trying to set headers
        $this->assertSame($fakeRedirector, $result);
    }

    // -- Static config caching --

    /**
     * Test isHeadersEnabled caches its value (static property).
     *
     * PHPStan change: Added static nullable property with ??= assignment.
     * Once resolved, the value persists across requests in an Octane worker.
     */
    public function test_headers_enabled_is_cached_across_calls(): void
    {
        // Arrange: enable headers
        config(['aicl.security.headers.enabled' => true]);
        $request = Request::create('/api/test', 'GET');

        // Act: first call caches the value
        $response1 = $this->middleware->handle($request, fn () => new Response('ok'));

        // Now change config (simulating a different request in Octane)
        config(['aicl.security.headers.enabled' => false]);

        // Act: second call should use cached value (still true)
        $response2 = $this->middleware->handle($request, fn () => new Response('ok'));

        // Assert: both responses have security headers (cached as enabled)
        $this->assertNotNull($response1->headers->get('X-Frame-Options'));
        $this->assertNotNull($response2->headers->get('X-Frame-Options'));
    }

    /**
     * Test resetCache clears all static cached values.
     *
     * PHPStan change: New resetCache() method added for testing.
     */
    public function test_reset_cache_clears_cached_state(): void
    {
        // Arrange: cache a value
        config(['aicl.security.headers.enabled' => true]);
        $request = Request::create('/api/test', 'GET');
        $this->middleware->handle($request, fn () => new Response('ok'));

        // Act: reset the cache
        SecurityHeadersMiddleware::resetCache();

        // Now disable headers and verify the new value is picked up
        config(['aicl.security.headers.enabled' => false]);
        $response = $this->middleware->handle($request, fn () => new Response('ok'));

        // Assert: headers not applied after reset + config change
        $this->assertNull($response->headers->get('X-Frame-Options'));
    }

    // -- CSP string caching --

    /**
     * Test Filament CSP string is cached after first build.
     *
     * PHPStan change: static ?string $filamentCsp caching property.
     */
    public function test_filament_csp_string_is_cached(): void
    {
        // Arrange: enable all security features
        config([
            'aicl.security.headers.enabled' => true,
            'aicl.security.csp.enabled' => true,
            'aicl.security.csp.report_only' => false,
        ]);
        $request = Request::create('/admin/dashboard', 'GET');

        // Act: make two requests to the admin panel path
        $response1 = $this->middleware->handle($request, fn () => new Response('ok'));
        $response2 = $this->middleware->handle($request, fn () => new Response('ok'));

        // Assert: both responses have the same CSP header value
        $csp1 = $response1->headers->get('Content-Security-Policy');
        $csp2 = $response2->headers->get('Content-Security-Policy');
        $this->assertNotNull($csp1);
        $this->assertSame($csp1, $csp2);
    }

    /**
     * Test API CSP differs from Filament CSP.
     *
     * Verifies the isFilamentRequest() path detection correctly
     * routes to different CSP profiles.
     */
    public function test_api_csp_differs_from_filament_csp(): void
    {
        // Arrange
        config([
            'aicl.security.headers.enabled' => true,
            'aicl.security.csp.enabled' => true,
            'aicl.security.csp.report_only' => false,
        ]);

        // Act: request to admin panel
        $adminRequest = Request::create('/admin/dashboard', 'GET');
        $adminResponse = $this->middleware->handle($adminRequest, fn () => new Response('ok'));
        $adminCsp = $adminResponse->headers->get('Content-Security-Policy');

        // Reset cache to get fresh API CSP
        SecurityHeadersMiddleware::resetCache();

        // Act: request to API endpoint
        $apiRequest = Request::create('/api/v1/data', 'GET');
        $apiResponse = $this->middleware->handle($apiRequest, fn () => new Response('ok'));
        $apiCsp = $apiResponse->headers->get('Content-Security-Policy');

        // Assert: CSP values differ between admin and API
        $this->assertNotNull($adminCsp);
        $this->assertNotNull($apiCsp);
        $this->assertNotSame($adminCsp, $apiCsp);
    }

    // -- HSTS caching --

    /**
     * Test HSTS max-age value is cached from config.
     *
     * PHPStan change: static ?int $hstsMaxAge caching property.
     */
    public function test_hsts_max_age_cached_from_config(): void
    {
        // Arrange
        config([
            'aicl.security.headers.enabled' => true,
            'aicl.security.headers.hsts' => true,
            'aicl.security.headers.hsts_max_age' => 86400, // 1 day
        ]);
        $request = Request::create('https://example.com/api/test', 'GET', [], [], [], [
            'HTTPS' => 'on',
        ]);

        // Act
        $response = $this->middleware->handle($request, fn () => new Response('ok'));

        // Assert: HSTS header includes custom max-age
        $hsts = $response->headers->get('Strict-Transport-Security');
        $this->assertNotNull($hsts);
        $this->assertStringContainsString('max-age=86400', $hsts);
    }
}
