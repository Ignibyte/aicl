<?php

namespace Aicl\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Symfony\Component\HttpFoundation\Response;

/**
 * Normalizes non-Response objects returned by the middleware pipeline.
 *
 * Livewire's SupportRedirects replaces Laravel's Redirector with its own
 * implementation. When Filament pages deny access (abort(403)), Livewire
 * intercepts and does `abort(redirect($to))`. This throws an
 * HttpResponseException containing a Livewire Redirector (which extends
 * Illuminate\Routing\Redirector, NOT Symfony Response). When this non-
 * Response object flows back through middleware like VerifyCsrfToken,
 * it crashes on `$response->headers`.
 *
 * This middleware converts Redirector objects to proper RedirectResponse
 * objects so downstream middleware can process them safely.
 *
 * Must be placed INSIDE the middleware stack, after VerifyCsrfToken.
 */
class NormalizeResponseMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // If the response is already a proper Symfony Response, pass it through
        if ($response instanceof Response) {
            return $response;
        }

        // Convert Redirector objects (including Livewire's) to proper RedirectResponse
        if ($response instanceof Redirector) {
            return new RedirectResponse(
                $request->getUri(),
                302,
            );
        }

        return $response;
    }
}
