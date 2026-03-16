<?php

namespace Aicl\Tests\Unit\Http;

use Aicl\Http\Middleware\SecurityHeadersMiddleware;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Tests\TestCase;

class SecurityHeadersMiddlewareTest extends TestCase
{
    protected SecurityHeadersMiddleware $middleware;

    protected function setUp(): void
    {
        parent::setUp();

        // Reset static cache between tests to prevent state leaking
        SecurityHeadersMiddleware::resetCache();

        $this->middleware = new SecurityHeadersMiddleware;
    }

    // ── Standard Headers ────────────────────────────────────

    public function test_adds_security_headers_when_enabled(): void
    {
        config(['aicl.security.headers.enabled' => true]);

        $request = Request::create('/api/v1/test', 'GET');
        $response = $this->middleware->handle($request, fn () => new Response('ok'));

        $this->assertSame('DENY', $response->headers->get('X-Frame-Options'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertSame('0', $response->headers->get('X-XSS-Protection'));
        $this->assertSame('strict-origin-when-cross-origin', $response->headers->get('Referrer-Policy'));
        $this->assertSame('camera=(), microphone=(), geolocation=()', $response->headers->get('Permissions-Policy'));
    }

    public function test_skips_headers_when_disabled(): void
    {
        config(['aicl.security.headers.enabled' => false]);

        $request = Request::create('/api/v1/test', 'GET');
        $response = $this->middleware->handle($request, fn () => new Response('ok'));

        $this->assertNull($response->headers->get('X-Frame-Options'));
        $this->assertNull($response->headers->get('X-Content-Type-Options'));
        $this->assertNull($response->headers->get('X-XSS-Protection'));
        $this->assertNull($response->headers->get('Referrer-Policy'));
        $this->assertNull($response->headers->get('Permissions-Policy'));
    }

    // ── HSTS ────────────────────────────────────────────────

    public function test_applies_hsts_header_on_secure_request(): void
    {
        config([
            'aicl.security.headers.enabled' => true,
            'aicl.security.headers.hsts' => true,
            'aicl.security.headers.hsts_max_age' => 31536000,
        ]);

        $request = Request::create('https://app.test/api/v1/test', 'GET');
        $response = $this->middleware->handle($request, fn () => new Response('ok'));

        $this->assertSame(
            'max-age=31536000; includeSubDomains',
            $response->headers->get('Strict-Transport-Security')
        );
    }

    public function test_skips_hsts_on_non_secure_request(): void
    {
        config([
            'aicl.security.headers.enabled' => true,
            'aicl.security.headers.hsts' => true,
        ]);

        $request = Request::create('http://app.test/api/v1/test', 'GET');
        $response = $this->middleware->handle($request, fn () => new Response('ok'));

        $this->assertNull($response->headers->get('Strict-Transport-Security'));
    }

    public function test_skips_hsts_when_disabled(): void
    {
        config([
            'aicl.security.headers.enabled' => true,
            'aicl.security.headers.hsts' => false,
        ]);

        $request = Request::create('https://app.test/api/v1/test', 'GET');
        $response = $this->middleware->handle($request, fn () => new Response('ok'));

        $this->assertNull($response->headers->get('Strict-Transport-Security'));
    }

    // ── CSP: Filament vs API ────────────────────────────────

    public function test_applies_filament_csp_for_admin_routes(): void
    {
        config([
            'aicl.security.headers.enabled' => true,
            'aicl.security.csp.enabled' => true,
            'aicl.security.csp.report_only' => false,
        ]);

        $request = Request::create('/admin/dashboard', 'GET');
        $response = $this->middleware->handle($request, fn () => new Response('ok'));

        $csp = $response->headers->get('Content-Security-Policy');

        $this->assertNotNull($csp);
        $this->assertStringContainsString("'unsafe-inline'", $csp);
        $this->assertStringContainsString("'unsafe-eval'", $csp);
        $this->assertStringContainsString('script-src', $csp);
        $this->assertStringContainsString('style-src', $csp);
    }

    public function test_applies_api_csp_for_non_admin_routes(): void
    {
        config([
            'aicl.security.headers.enabled' => true,
            'aicl.security.csp.enabled' => true,
            'aicl.security.csp.report_only' => false,
        ]);

        $request = Request::create('/api/users', 'GET');
        $response = $this->middleware->handle($request, fn () => new Response('ok'));

        $csp = $response->headers->get('Content-Security-Policy');

        $this->assertNotNull($csp);
        $this->assertStringContainsString("default-src 'none'", $csp);
        $this->assertStringNotContainsString("'unsafe-inline'", $csp);
        $this->assertStringNotContainsString("'unsafe-eval'", $csp);
    }

    // ── CSP: Report-Only vs Enforce ─────────────────────────

    public function test_csp_report_only_mode(): void
    {
        config([
            'aicl.security.headers.enabled' => true,
            'aicl.security.csp.enabled' => true,
            'aicl.security.csp.report_only' => true,
        ]);

        $request = Request::create('/api/v1/test', 'GET');
        $response = $this->middleware->handle($request, fn () => new Response('ok'));

        $this->assertNotNull($response->headers->get('Content-Security-Policy-Report-Only'));
        $this->assertNull($response->headers->get('Content-Security-Policy'));
    }

    public function test_csp_enforce_mode(): void
    {
        config([
            'aicl.security.headers.enabled' => true,
            'aicl.security.csp.enabled' => true,
            'aicl.security.csp.report_only' => false,
        ]);

        $request = Request::create('/api/v1/test', 'GET');
        $response = $this->middleware->handle($request, fn () => new Response('ok'));

        $this->assertNotNull($response->headers->get('Content-Security-Policy'));
        $this->assertNull($response->headers->get('Content-Security-Policy-Report-Only'));
    }

    // ── CSP: Disabled ───────────────────────────────────────

    public function test_skips_csp_when_disabled(): void
    {
        config([
            'aicl.security.headers.enabled' => true,
            'aicl.security.csp.enabled' => false,
        ]);

        $request = Request::create('/api/v1/test', 'GET');
        $response = $this->middleware->handle($request, fn () => new Response('ok'));

        $this->assertNull($response->headers->get('Content-Security-Policy'));
        $this->assertNull($response->headers->get('Content-Security-Policy-Report-Only'));
    }
}
