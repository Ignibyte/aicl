<?php

namespace Aicl\Tests\Feature\Http;

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

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'Aicl\Database\Seeders\RoleSeeder']);

        Event::fake([
            EntityCreated::class,
            EntityUpdated::class,
            EntityDeleted::class,
        ]);
    }

    // ── Unauthenticated Requests ────────────────────────────

    public function test_skips_unauthenticated_requests(): void
    {
        $registry = $this->createMock(PresenceRegistry::class);
        $registry->expects($this->never())->method('touch');

        $request = Request::create('https://app.test/admin/dashboard', 'GET');
        $request->setLaravelSession(app('session.store'));

        $middleware = new TrackPresenceMiddleware($registry);
        $response = $middleware->handle($request, fn () => new Response('ok'));

        $this->assertSame(200, $response->getStatusCode());
    }

    // ── Authenticated Tracking ──────────────────────────────

    public function test_tracks_authenticated_user(): void
    {
        $user = User::factory()->create(['name' => 'Tracked User', 'email' => 'tracked@example.com']);
        $user->assignRole('admin');

        $registry = $this->createMock(PresenceRegistry::class);
        $registry->expects($this->once())
            ->method('touch')
            ->with(
                $this->isType('string'),
                $this->equalTo($user->getKey()),
                $this->callback(function (array $meta) {
                    return $meta['user_name'] === 'Tracked User'
                        && $meta['user_email'] === 'tracked@example.com'
                        && array_key_exists('current_url', $meta)
                        && array_key_exists('ip_address', $meta);
                })
            );

        $request = $this->createAuthenticatedRequest($user, 'https://app.test/admin/dashboard');

        $middleware = new TrackPresenceMiddleware($registry);
        $middleware->handle($request, fn () => new Response('ok'));
    }

    // ── Throttle Behavior ───────────────────────────────────

    public function test_throttles_writes_within_30_seconds(): void
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

    public function test_allows_write_after_throttle_expires(): void
    {
        $user = User::factory()->create(['name' => 'Expired User', 'email' => 'expired@example.com']);
        $user->assignRole('admin');

        $registry = $this->createMock(PresenceRegistry::class);
        $registry->expects($this->once())
            ->method('touch')
            ->with(
                $this->isType('string'),
                $this->equalTo($user->getKey()),
                $this->isType('array')
            );

        $request = $this->createAuthenticatedRequest($user, 'https://app.test/admin/users');
        $sessionId = $request->session()->getId();
        $throttleKey = 'presence:throttle:'.$sessionId;

        // Ensure the throttle key does NOT exist (simulating expiry)
        Cache::forget($throttleKey);

        $middleware = new TrackPresenceMiddleware($registry);
        $middleware->handle($request, fn () => new Response('ok'));
    }

    // ── Session Data ────────────────────────────────────────

    public function test_passes_correct_session_data(): void
    {
        $user = User::factory()->create(['name' => 'Session User', 'email' => 'session@example.com']);
        $user->assignRole('admin');

        $registry = $this->createMock(PresenceRegistry::class);
        $registry->expects($this->once())
            ->method('touch')
            ->with(
                $this->isType('string'),
                $this->equalTo($user->getKey()),
                $this->callback(function (array $meta) {
                    return $meta['user_name'] === 'Session User'
                        && $meta['user_email'] === 'session@example.com'
                        && $meta['current_url'] === 'https://app.test/admin/settings'
                        && $meta['ip_address'] === '192.168.1.100';
                })
            );

        $request = $this->createAuthenticatedRequest(
            $user,
            'https://app.test/admin/settings',
            null,
            '192.168.1.100'
        );

        $middleware = new TrackPresenceMiddleware($registry);
        $middleware->handle($request, fn () => new Response('ok'));
    }

    // ── Helpers ─────────────────────────────────────────────

    protected function createAuthenticatedRequest(
        User $user,
        string $url,
        ?string $sessionId = null,
        ?string $ip = null,
    ): Request {
        $serverParams = [];
        if ($ip !== null) {
            $serverParams['REMOTE_ADDR'] = $ip;
        }

        $request = Request::create($url, 'GET', [], [], [], $serverParams);
        $request->setUserResolver(fn () => $user);

        $session = app('session.store');
        if ($sessionId) {
            $session->setId($sessionId);
        }
        $request->setLaravelSession($session);

        return $request;
    }
}
