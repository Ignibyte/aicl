<?php

declare(strict_types=1);

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
    /**
     * Log the API request with method, path, status, user, IP, and duration.
     *
     * @param Request $request The incoming HTTP request
     * @param Closure $next    The next middleware in the pipeline
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $startTime = microtime(true);

        $response = $next($request);

        // Guard against non-Response objects (e.g., Livewire Redirector)
        if (! $response instanceof Response) {
            // @codeCoverageIgnoreStart — Untestable in unit context
            return $response;
            // @codeCoverageIgnoreEnd
        }

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
