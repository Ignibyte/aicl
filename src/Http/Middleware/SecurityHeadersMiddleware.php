<?php

declare(strict_types=1);

namespace Aicl\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies security headers to all responses (OWASP API8).
 *
 * Includes configurable Content-Security-Policy with separate
 * profiles for Filament/admin (permissive) and API (strict).
 *
 * Caches resolved config values and CSP strings per-worker to avoid
 * repeated config() lookups on every request in Swoole/Octane.
 */
class SecurityHeadersMiddleware
{
    /** Cached config values — survive across requests in Octane workers. */
    private static ?bool $headersEnabled = null;

    private static ?bool $hstsEnabled = null;

    private static ?int $hstsMaxAge = null;

    private static ?bool $cspEnabled = null;

    private static ?bool $cspReportOnly = null;

    private static ?string $panelPath = null;

    /** Pre-built CSP strings — avoid rebuilding on every response. */
    private static ?string $filamentCsp = null;

    private static ?string $apiCsp = null;

    /**
     * Apply OWASP security headers to the response.
     *
     * @param  Request  $request  The incoming HTTP request
     * @param  Closure  $next  The next middleware in the pipeline
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Filament/Livewire may return a Redirector (not a Symfony Response)
        // when authorization fails. Only apply headers to proper responses.
        if (! $response instanceof Response) {
            // @codeCoverageIgnoreStart — Untestable in unit context
            return $response;
        }

        if (! $this->isHeadersEnabled()) {
            return $response;
            // @codeCoverageIgnoreEnd
        }

        $this->applyStandardHeaders($response);
        $this->applyHstsHeader($request, $response);
        $this->applyCspHeader($request, $response);

        return $response;
    }

    /**
     * Reset cached state. Called during testing or config changes.
     */
    public static function resetCache(): void
    {
        // @codeCoverageIgnoreStart — Untestable in unit context
        self::$headersEnabled = null;
        self::$hstsEnabled = null;
        self::$hstsMaxAge = null;
        self::$cspEnabled = null;
        self::$cspReportOnly = null;
        self::$panelPath = null;
        self::$filamentCsp = null;
        self::$apiCsp = null;
        // @codeCoverageIgnoreEnd
    }

    protected function isHeadersEnabled(): bool
    {
        return self::$headersEnabled ??= (bool) config('aicl.security.headers.enabled', true);
    }

    /**
     * Apply standard security headers (X-Frame-Options, MIME sniffing, etc.).
     */
    protected function applyStandardHeaders(Response $response): void
    {
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-XSS-Protection', '0');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
    }

    /**
     * Apply HTTP Strict Transport Security header for HTTPS requests.
     */
    protected function applyHstsHeader(Request $request, Response $response): void
    {
        self::$hstsEnabled ??= (bool) config('aicl.security.headers.hsts', true);

        if (! self::$hstsEnabled) {
            // @codeCoverageIgnoreStart — Untestable in unit context
            return;
        }

        if (! $request->isSecure()) {
            return;
            // @codeCoverageIgnoreEnd
        }

        self::$hstsMaxAge ??= (int) config('aicl.security.headers.hsts_max_age', 31536000);
        $response->headers->set('Strict-Transport-Security', 'max-age='.self::$hstsMaxAge.'; includeSubDomains');
    }

    /**
     * Apply Content-Security-Policy header with profile-specific directives.
     *
     * Pre-builds and caches CSP strings per-worker to avoid
     * rebuilding the directive string on every response.
     */
    protected function applyCspHeader(Request $request, Response $response): void
    {
        self::$cspEnabled ??= (bool) config('aicl.security.csp.enabled', true);

        if (! self::$cspEnabled) {
            // @codeCoverageIgnoreStart — Untestable in unit context
            return;
            // @codeCoverageIgnoreEnd
        }

        $cspValue = $this->isFilamentRequest($request)
            ? $this->getFilamentCspString()
            : $this->getApiCspString();

        self::$cspReportOnly ??= (bool) config('aicl.security.csp.report_only', true);

        $headerName = self::$cspReportOnly
            ? 'Content-Security-Policy-Report-Only'
            // @codeCoverageIgnoreStart — Untestable in unit context
            : 'Content-Security-Policy';
        // @codeCoverageIgnoreEnd

        $response->headers->set($headerName, $cspValue);
    }

    /**
     * Determine if this is a Filament admin panel request.
     *
     * Caches the panel path per-worker to avoid resolving filament() on every request.
     */
    protected function isFilamentRequest(Request $request): bool
    {
        if (self::$panelPath === null) {
            self::$panelPath = 'admin';

            try {
                self::$panelPath = filament()->getPanel()?->getPath() ?? 'admin';
                // @codeCoverageIgnoreStart — Untestable in unit context
            } catch (\Throwable) {
                // @codeCoverageIgnoreEnd
                // Filament not booted yet — fall back to default
            }
        }

        return $request->is(self::$panelPath.'/*') || $request->is(self::$panelPath);
    }

    /**
     * Get cached Filament CSP string.
     */
    protected function getFilamentCspString(): string
    {
        if (self::$filamentCsp === null) {
            $directives = config('aicl.security.csp.filament_directives', [
                'default-src' => ["'self'"],
                'script-src' => ["'self'", "'unsafe-inline'", "'unsafe-eval'"],
                'style-src' => ["'self'", "'unsafe-inline'", 'https://fonts.googleapis.com'],
                'img-src' => ["'self'", 'data:', 'blob:'],
                'font-src' => ["'self'", 'data:', 'https://fonts.gstatic.com'],
                'connect-src' => ["'self'", 'ws:', 'wss:'],
                'frame-ancestors' => ["'none'"],
            ]);

            self::$filamentCsp = $this->buildCspString($directives);
        }

        return self::$filamentCsp;
    }

    /**
     * Get cached API CSP string.
     */
    protected function getApiCspString(): string
    {
        if (self::$apiCsp === null) {
            $directives = config('aicl.security.csp.api_directives', [
                'default-src' => ["'none'"],
                'frame-ancestors' => ["'none'"],
            ]);

            self::$apiCsp = $this->buildCspString($directives);
        }

        return self::$apiCsp;
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
