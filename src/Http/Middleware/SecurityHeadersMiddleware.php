<?php

namespace Aicl\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies security headers to all responses (OWASP API8).
 *
 * Includes configurable Content-Security-Policy with separate
 * profiles for Filament/admin (permissive) and API (strict).
 */
class SecurityHeadersMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Filament/Livewire may return a Redirector (not a Symfony Response)
        // when authorization fails. Only apply headers to proper responses.
        if (! $response instanceof Response) {
            return $response;
        }

        if (! config('aicl.security.headers.enabled', true)) {
            return $response;
        }

        $this->applyStandardHeaders($response);
        $this->applyHstsHeader($request, $response);
        $this->applyCspHeader($request, $response);

        return $response;
    }

    protected function applyStandardHeaders(Response $response): void
    {
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-XSS-Protection', '0');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
    }

    protected function applyHstsHeader(Request $request, Response $response): void
    {
        if (! config('aicl.security.headers.hsts', true)) {
            return;
        }

        if (! $request->isSecure()) {
            return;
        }

        $maxAge = config('aicl.security.headers.hsts_max_age', 31536000);
        $response->headers->set('Strict-Transport-Security', "max-age={$maxAge}; includeSubDomains");
    }

    protected function applyCspHeader(Request $request, Response $response): void
    {
        if (! config('aicl.security.csp.enabled', true)) {
            return;
        }

        $directives = $this->isFilamentRequest($request)
            ? $this->getFilamentCspDirectives()
            : $this->getApiCspDirectives();

        $cspValue = $this->buildCspString($directives);

        $headerName = config('aicl.security.csp.report_only', true)
            ? 'Content-Security-Policy-Report-Only'
            : 'Content-Security-Policy';

        $response->headers->set($headerName, $cspValue);
    }

    protected function isFilamentRequest(Request $request): bool
    {
        return $request->is('admin/*') || $request->is('admin');
    }

    /**
     * CSP for Filament/admin panel — permissive for Livewire/Alpine.
     *
     * @return array<string, list<string>>
     */
    protected function getFilamentCspDirectives(): array
    {
        return config('aicl.security.csp.filament_directives', [
            'default-src' => ["'self'"],
            'script-src' => ["'self'", "'unsafe-inline'", "'unsafe-eval'"],
            'style-src' => ["'self'", "'unsafe-inline'", 'https://fonts.googleapis.com'],
            'img-src' => ["'self'", 'data:', 'blob:'],
            'font-src' => ["'self'", 'data:', 'https://fonts.gstatic.com'],
            'connect-src' => ["'self'", 'ws:', 'wss:'],
            'frame-ancestors' => ["'none'"],
        ]);
    }

    /**
     * CSP for API endpoints — strict.
     *
     * @return array<string, list<string>>
     */
    protected function getApiCspDirectives(): array
    {
        return config('aicl.security.csp.api_directives', [
            'default-src' => ["'none'"],
            'frame-ancestors' => ["'none'"],
        ]);
    }

    /**
     * @param  array<string, list<string>>  $directives
     */
    protected function buildCspString(array $directives): string
    {
        $parts = [];

        foreach ($directives as $directive => $values) {
            $parts[] = $directive.' '.implode(' ', $values);
        }

        return implode('; ', $parts);
    }
}
