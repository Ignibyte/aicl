<?php

namespace Aicl\Http\Middleware;

use Aicl\Services\PresenceRegistry;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tracks authenticated admin sessions in the PresenceRegistry.
 *
 * Writes are throttled to once per 30 seconds per session to avoid
 * excessive Redis writes on every request.
 *
 * No return type hint: Filament/Livewire may return a Redirector (not a
 * Symfony Response) when access is denied, so the middleware must pass
 * through whatever $next() returns.
 */
class TrackPresenceMiddleware
{
    protected const THROTTLE_SECONDS = 30;

    public function __construct(protected PresenceRegistry $registry) {}

    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Filament/Livewire may return a Redirector (not a Symfony Response)
        // when authorization fails. Only track presence for proper responses.
        if (! $response instanceof Response) {
            return $response;
        }

        $user = $request->user();

        if (! $user) {
            return $response;
        }

        $sessionId = $request->session()->getId();
        $throttleKey = 'presence:throttle:'.$sessionId;

        if (Cache::has($throttleKey)) {
            return $response;
        }

        Cache::put($throttleKey, true, self::THROTTLE_SECONDS);

        $this->registry->touch($sessionId, $user->getKey(), [
            'user_name' => $user->name,
            'user_email' => $user->email,
            'current_url' => $request->url(),
            'ip_address' => $request->ip(),
        ]);

        return $response;
    }
}
