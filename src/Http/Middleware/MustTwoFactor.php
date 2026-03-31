<?php

declare(strict_types=1);

namespace Aicl\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Jeffgreco13\FilamentBreezy\BreezyCore;

/**
 * Fixed MustTwoFactor middleware that removes the incorrect `: Response`
 * return type from Breezy v3.1.1's handle() method. The vendor version
 * declares `Response` but redirect()->route() returns a `Redirector`,
 * causing a 500 error.
 *
 * Registered via BreezyCore::enableTwoFactorAuthentication($authMiddleware).
 */
class MustTwoFactor
{
    /**
     * Resolve the two-factor route and profile route parameters for the current panel.
     *
     * Returns null for $twoFactorRoute when tenancy is active but no tenant is present
     * (caller should pass through to $next in that case).
     *
     * @codeCoverageIgnore Reason: framework-bootstrap -- Requires Filament tenancy and 2FA session state
     *
     * @return array{twoFactorRoute: string|null, myProfileRouteName: string, myProfileRouteParameters: array<string, mixed>}
     */
    private function resolveTwoFactorRoutes(string $panelId, BreezyCore $breezy): array
    {
        $myProfileRouteName = 'filament.'.$panelId.'.pages.'.$breezy->slug();
        $myProfileRouteParameters = [];
        $twoFactorRoute = null;

        if (! filament()->hasTenancy()) {
            $twoFactorRoute = route('filament.'.$panelId.'.auth.two-factor', ['next' => request()->getRequestUri()]);

            return compact('twoFactorRoute', 'myProfileRouteName', 'myProfileRouteParameters');
        }

        // @codeCoverageIgnoreStart — Untestable in unit context
        $tenantId = request()->route()?->parameter('tenant');

        if ($tenantId) {
            $myProfileRouteParameters = ['tenant' => $tenantId];
            $twoFactorRoute = route('filament.'.$panelId.'.auth.two-factor', ['tenant' => $tenantId, 'next' => request()->getRequestUri()]);
        }
        // @codeCoverageIgnoreEnd

        return compact('twoFactorRoute', 'myProfileRouteName', 'myProfileRouteParameters');
    }

    /**
     * Determine whether a two-factor redirect is required and return it, or null to continue.
     *
     * @param array{twoFactorRoute: string|null, myProfileRouteName: string, myProfileRouteParameters: array<string, mixed>} $resolved
     *
     * @codeCoverageIgnore Reason: framework-bootstrap -- Requires Filament tenancy and 2FA session state
     */
    private function resolveTwoFactorRedirect(Request $request, BreezyCore $breezy, array $resolved): mixed
    {
        $myProfileRouteName = $resolved['myProfileRouteName'];
        $myProfileRouteParameters = $resolved['myProfileRouteParameters'];
        $twoFactorRoute = $resolved['twoFactorRoute'];

        // @codeCoverageIgnoreStart — Untestable in unit context
        if (filament()->hasTenancy() && $twoFactorRoute === null) {
            return null;
        }

        if ($breezy->shouldForceTwoFactor() && ! $request->routeIs($myProfileRouteName)) {
            return redirect()->route($myProfileRouteName, $myProfileRouteParameters);
        }

        $user = filament()->auth()->user();

        if ($user && $user->hasConfirmedTwoFactor() && ! $user->hasValidTwoFactorSession()) {
            return redirect($twoFactorRoute);
        }
        // @codeCoverageIgnoreEnd

        return null;
    }

    /**
     * @codeCoverageIgnore Reason: framework-bootstrap -- Requires Filament tenancy and 2FA session state
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $route = $request->route();

        if (
            ! filament()->auth()->check() ||
            ! $route ||
            str($route->getName())->contains('logout')
        ) {
            return $next($request);
        }

        /** @var BreezyCore $breezy */
        $breezy = filament('filament-breezy');

        $panel = filament()->getCurrentOrDefaultPanel();
        $panelId = $panel?->getId() ?? 'admin';

        $resolved = $this->resolveTwoFactorRoutes($panelId, $breezy);
        $redirect = $this->resolveTwoFactorRedirect($request, $breezy, $resolved);

        if ($redirect !== null) {
            return $redirect;
        }

        return $next($request);
    }
}
