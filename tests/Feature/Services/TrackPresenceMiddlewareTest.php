<?php

namespace Aicl\Tests\Feature\Services;

use Aicl\Events\EntityCreated;
use Aicl\Events\EntityDeleted;
use Aicl\Events\EntityUpdated;
use Aicl\Http\Middleware\TrackPresenceMiddleware;
use Aicl\Services\PresenceRegistry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class TrackPresenceMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected PresenceRegistry $registry;

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

        $this->registry = app(PresenceRegistry::class);
        Cache::forget('presence:session_index');
    }

    // ── Basic behavior ──────────────────────────────────────

    public function test_middleware_tracks_authenticated_user(): void
    {
        $user = User::factory()->create(['name' => 'John Doe', 'email' => 'john@example.com']);
        $user->assignRole('admin');

        $request = $this->createAuthenticatedRequest($user, 'https://app.test/admin/dashboard');

        $this->invokeMiddleware($request);

        $sessions = $this->registry->allSessions();

        $this->assertCount(1, $sessions);
        $this->assertSame($user->getKey(), $sessions->first()['user_id']);
        $this->assertSame('John Doe', $sessions->first()['user_name']);
        $this->assertSame('john@example.com', $sessions->first()['user_email']);
        $this->assertSame('https://app.test/admin/dashboard', $sessions->first()['current_url']);
    }

    public function test_middleware_skips_unauthenticated_requests(): void
    {
        $request = Request::create('https://app.test/admin/dashboard', 'GET');
        $request->setLaravelSession(app('session.store'));

        $this->invokeMiddleware($request);

        $sessions = $this->registry->allSessions();

        $this->assertTrue($sessions->isEmpty());
    }

    // ── Throttle behavior ───────────────────────────────────

    public function test_middleware_throttles_writes_to_once_per_30_seconds(): void
    {
        $user = User::factory()->create(['name' => 'Jane', 'email' => 'jane@example.com']);
        $user->assignRole('admin');

        $request = $this->createAuthenticatedRequest($user, 'https://app.test/admin/dashboard');

        // First request should write
        $this->invokeMiddleware($request);
        $sessions = $this->registry->allSessions();
        $this->assertCount(1, $sessions);
        $this->assertSame('https://app.test/admin/dashboard', $sessions->first()['current_url']);

        // Second request within 30s should NOT update
        $request2 = $this->createAuthenticatedRequest($user, 'https://app.test/admin/users', $request->session()->getId());
        $this->invokeMiddleware($request2);

        $sessions = $this->registry->allSessions();
        $this->assertSame('https://app.test/admin/dashboard', $sessions->first()['current_url']);
    }

    public function test_middleware_writes_again_after_throttle_expires(): void
    {
        $user = User::factory()->create(['name' => 'Jane', 'email' => 'jane@example.com']);
        $user->assignRole('admin');

        $request = $this->createAuthenticatedRequest($user, 'https://app.test/admin/dashboard');
        $this->invokeMiddleware($request);

        // Advance time past throttle
        $this->travel(31)->seconds();

        $request2 = $this->createAuthenticatedRequest($user, 'https://app.test/admin/users', $request->session()->getId());
        $this->invokeMiddleware($request2);

        $sessions = $this->registry->allSessions();
        $this->assertSame('https://app.test/admin/users', $sessions->first()['current_url']);
    }

    // ── Response passthrough ────────────────────────────────

    public function test_middleware_passes_response_through(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        $request = $this->createAuthenticatedRequest($user, 'https://app.test/admin/dashboard');

        $response = (new TrackPresenceMiddleware($this->registry))
            ->handle($request, fn () => new Response('OK', 200));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('OK', $response->getContent());
    }

    // ── Middleware alias registration ────────────────────────

    public function test_track_presence_middleware_alias_is_registered(): void
    {
        /** @var \Illuminate\Routing\Router $router */
        $router = app('router');

        $middlewareMap = $router->getMiddleware();

        $this->assertArrayHasKey('track-presence', $middlewareMap);
        $this->assertSame(TrackPresenceMiddleware::class, $middlewareMap['track-presence']);
    }

    // ── Helpers ─────────────────────────────────────────────

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

    protected function invokeMiddleware(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        return (new TrackPresenceMiddleware($this->registry))
            ->handle($request, fn () => new Response('OK', 200));
    }
}
