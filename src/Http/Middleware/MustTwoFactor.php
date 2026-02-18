<?php

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
    public function handle(Request $request, Closure $next)
    {
        if (
            filament()->auth()->check() &&
            ! str($request->route()->getName())->contains('logout')
        ) {
            /** @var BreezyCore $breezy */
            $breezy = filament('filament-breezy');

            $myProfileRouteName = 'filament.'.filament()->getCurrentOrDefaultPanel()->getId().'.pages.'.$breezy->slug();

            $myProfileRouteParameters = [];

            if (filament()->hasTenancy()) {
                if (! $tenantId = request()->route()->parameter('tenant')) {
                    return $next($request);
                }
                $myProfileRouteParameters = ['tenant' => $tenantId];
                $twoFactorRoute = route('filament.'.filament()->getCurrentOrDefaultPanel()->getId().'.auth.two-factor', ['tenant' => $tenantId, 'next' => request()->getRequestUri()]);
            } else {
                $twoFactorRoute = route('filament.'.filament()->getCurrentOrDefaultPanel()->getId().'.auth.two-factor', ['next' => request()->getRequestUri()]);
            }

            if ($breezy->shouldForceTwoFactor() && ! $request->routeIs($myProfileRouteName)) {
                return redirect()->route($myProfileRouteName, $myProfileRouteParameters);
            } elseif (filament()->auth()->user()->hasConfirmedTwoFactor() && ! filament()->auth()->user()->hasValidTwoFactorSession()) {
                return redirect($twoFactorRoute);
            }
        }

        return $next($request);
    }
}
