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
 */
class TrackPresenceMiddleware
{
    protected const THROTTLE_SECONDS = 30;

    public function __construct(protected PresenceRegistry $registry) {}

    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

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
