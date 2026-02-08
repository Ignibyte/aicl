<?php

namespace Aicl\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Logs API requests for security monitoring.
 *
 * Records endpoint, HTTP method, user ID, IP, response status,
 * and request duration to a dedicated log channel.
 */
class ApiRequestLogMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);

        /** @var Response $response */
        $response = $next($request);

        $duration = round((microtime(true) - $startTime) * 1000, 2);

        Log::channel('api-requests')->info('API Request', [
            'method' => $request->method(),
            'path' => $request->path(),
            'status' => $response->getStatusCode(),
            'user_id' => $request->user()?->getKey(),
            'ip' => $request->ip(),
            'duration_ms' => $duration,
            'user_agent' => $request->userAgent(),
        ]);

        return $response;
    }
}
