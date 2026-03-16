<?php

namespace Aicl\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Normalizes non-Response objects (e.g. Livewire\Redirector) into proper HTTP responses.
 *
 * Livewire/Filament may return a Livewire\Features\SupportRedirects\Redirector
 * instead of an Illuminate\Http\RedirectResponse when authorization fails.
 * Downstream middleware (VerifyCsrfToken, EncryptCookies) assumes a Symfony Response
 * with a $headers property — accessing it on a Redirector causes a 500.
 *
 * Place this middleware immediately AFTER VerifyCsrfToken in the panel middleware
 * stack so it normalizes responses before VerifyCsrfToken processes them.
 *
 * @see https://github.com/livewire/livewire/issues/... (known Livewire/Octane interaction)
 */
class NormalizeResponseMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Request  $request  The incoming HTTP request
     * @param  Closure  $next  The next middleware in the pipeline
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($response instanceof Response) {
            return $response;
        }

        // Livewire Redirector — extract the target URL and return a proper RedirectResponse
        if (method_exists($response, 'getTargetUrl')) {
            return new RedirectResponse($response->getTargetUrl());
        }

        // Generic fallback — convert to a redirect to the current URL
        return new RedirectResponse($request->fullUrl());
    }
}
