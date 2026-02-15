<?php

namespace Aicl\Tests\Feature\Http\Middleware;

use Aicl\Events\EntityCreated;
use Aicl\Events\EntityDeleted;
use Aicl\Events\EntityUpdated;
use Aicl\Http\Middleware\ApiRequestLogMiddleware;
use Aicl\Http\Middleware\SecurityHeadersMiddleware;
use Aicl\Http\Middleware\TrackPresenceMiddleware;
use Aicl\Services\PresenceRegistry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class MiddlewareTest extends TestCase
{
    use RefreshDatabase;

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
    }

    // ─── SecurityHeadersMiddleware ──────────────────────────

    public function test_security_headers_are_applied(): void
    {
        config(['aicl.security.headers.enabled' => true]);

        $middleware = new SecurityHeadersMiddleware;
        $request = Request::create('/api/v1/test', 'GET');
        $response = $middleware->handle($request, fn () => new Response('ok'));

        $this->assertSame('DENY', $response->headers->get('X-Frame-Options'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertSame('0', $response->headers->get('X-XSS-Protection'));
        $this->assertSame('strict-origin-when-cross-origin', $response->headers->get('Referrer-Policy'));
        $this->assertSame('camera=(), microphone=(), geolocation=()', $response->headers->get('Permissions-Policy'));
    }

    public function test_security_headers_can_be_disabled(): void
    {
        config(['aicl.security.headers.enabled' => false]);

        $middleware = new SecurityHeadersMiddleware;
        $request = Request::create('/api/v1/test', 'GET');
        $response = $middleware->handle($request, fn () => new Response('ok'));

        $this->assertNull($response->headers->get('X-Frame-Options'));
        $this->assertNull($response->headers->get('X-Content-Type-Options'));
        $this->assertNull($response->headers->get('X-XSS-Protection'));
        $this->assertNull($response->headers->get('Referrer-Policy'));
        $this->assertNull($response->headers->get('Permissions-Policy'));
    }

    public function test_csp_header_is_applied_in_report_only_mode(): void
    {
        config([
            'aicl.security.headers.enabled' => true,
            'aicl.security.csp.enabled' => true,
            'aicl.security.csp.report_only' => true,
        ]);

        $middleware = new SecurityHeadersMiddleware;
        $request = Request::create('/api/v1/test', 'GET');
        $response = $middleware->handle($request, fn () => new Response('ok'));

        $this->assertNotNull($response->headers->get('Content-Security-Policy-Report-Only'));
        $this->assertNull($response->headers->get('Content-Security-Policy'));
    }

    public function test_csp_header_is_enforced_when_report_only_disabled(): void
    {
        config([
            'aicl.security.headers.enabled' => true,
            'aicl.security.csp.enabled' => true,
            'aicl.security.csp.report_only' => false,
        ]);

        $middleware = new SecurityHeadersMiddleware;
        $request = Request::create('/api/v1/test', 'GET');
        $response = $middleware->handle($request, fn () => new Response('ok'));

        $this->assertNotNull($response->headers->get('Content-Security-Policy'));
        $this->assertNull($response->headers->get('Content-Security-Policy-Report-Only'));
    }

    public function test_hsts_header_applied_on_secure_request(): void
    {
        config([
            'aicl.security.headers.enabled' => true,
            'aicl.security.headers.hsts' => true,
            'aicl.security.headers.hsts_max_age' => 31536000,
        ]);

        $middleware = new SecurityHeadersMiddleware;
        $request = Request::create('https://app.test/api/v1/test', 'GET');
        $response = $middleware->handle($request, fn () => new Response('ok'));

        $this->assertSame(
            'max-age=31536000; includeSubDomains',
            $response->headers->get('Strict-Transport-Security')
        );
    }

    public function test_hsts_header_not_applied_on_insecure_request(): void
    {
        config([
            'aicl.security.headers.enabled' => true,
            'aicl.security.headers.hsts' => true,
        ]);

        $middleware = new SecurityHeadersMiddleware;
        $request = Request::create('http://app.test/api/v1/test', 'GET');
        $response = $middleware->handle($request, fn () => new Response('ok'));

        $this->assertNull($response->headers->get('Strict-Transport-Security'));
    }

    // ─── ApiRequestLogMiddleware ────────────────────────────

    public function test_api_request_is_logged(): void
    {
        $channelMock = \Mockery::mock(\Psr\Log\LoggerInterface::class);
        $channelMock->shouldReceive('info')
            ->once()
            ->with('API Request', \Mockery::on(function (array $context) {
                return $context['method'] === 'GET'
                    && $context['path'] === 'api/v1/test'
                    && $context['status'] === 200
                    && array_key_exists('duration_ms', $context);
            }));

        Log::shouldReceive('channel')
            ->with('api-requests')
            ->once()
            ->andReturn($channelMock);

        $middleware = new ApiRequestLogMiddleware;
        $request = Request::create('/api/v1/test', 'GET');
        $response = $middleware->handle($request, fn () => new Response('ok'));

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_api_request_log_middleware_returns_response(): void
    {
        $channelMock = \Mockery::mock(\Psr\Log\LoggerInterface::class);
        $channelMock->shouldReceive('info')->once();

        Log::shouldReceive('channel')
            ->with('api-requests')
            ->once()
            ->andReturn($channelMock);

        $middleware = new ApiRequestLogMiddleware;
        $request = Request::create('/api/v1/users', 'POST');
        $response = $middleware->handle($request, fn () => new Response('created', 201));

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame('created', $response->getContent());
    }

    // ─── TrackPresenceMiddleware ────────────────────────────

    public function test_presence_middleware_tracks_authenticated_user(): void
    {
        $user = User::factory()->create(['name' => 'Test User', 'email' => 'test@example.com']);
        $user->assignRole('admin');

        $registry = $this->createMock(PresenceRegistry::class);
        $registry->expects($this->once())
            ->method('touch')
            ->with(
                $this->isType('string'),
                $this->equalTo($user->getKey()),
                $this->callback(function (array $meta) {
                    return $meta['user_name'] === 'Test User'
                        && $meta['user_email'] === 'test@example.com';
                })
            );

        $middleware = new TrackPresenceMiddleware($registry);
        $request = $this->createAuthenticatedRequest($user, 'https://app.test/admin/dashboard');

        $middleware->handle($request, fn () => new Response('ok'));
    }

    public function test_presence_middleware_skips_unauthenticated_request(): void
    {
        $registry = $this->createMock(PresenceRegistry::class);
        $registry->expects($this->never())->method('touch');

        $middleware = new TrackPresenceMiddleware($registry);
        $request = Request::create('https://app.test/admin/dashboard', 'GET');
        $request->setLaravelSession(app('session.store'));

        $middleware->handle($request, fn () => new Response('ok'));
    }

    public function test_presence_middleware_throttles_writes(): void
    {
        $user = User::factory()->create(['name' => 'Throttle User', 'email' => 'throttle@example.com']);
        $user->assignRole('admin');

        $request = $this->createAuthenticatedRequest($user, 'https://app.test/admin/dashboard');
        $sessionId = $request->session()->getId();
        $throttleKey = 'presence:throttle:'.$sessionId;

        // Pre-set the throttle key to simulate a recent write
        Cache::put($throttleKey, true, 30);

        $registry = $this->createMock(PresenceRegistry::class);
        $registry->expects($this->never())->method('touch');

        $middleware = new TrackPresenceMiddleware($registry);
        $middleware->handle($request, fn () => new Response('ok'));
    }

    // ─── Helpers ─────────────────────────────────────────────

    protected function createAuthenticatedRequest(User $user, string $url, ?string $sessionId = null): Request
    {
        $request = Request::create($url, 'GET');
        $request->setUserResolver(fn () => $user);

        $session = app('session.store');
        if ($sessionId) {
            $session->setId($sessionId);
        }
        $request->setLaravelSession($session);

        return $request;
    }
}
