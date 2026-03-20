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
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $route = $request->route();

        if (
            filament()->auth()->check() &&
            $route &&
            ! str($route->getName())->contains('logout')
        ) {
            /** @var BreezyCore $breezy */
            $breezy = filament('filament-breezy');

            $panel = filament()->getCurrentOrDefaultPanel();
            $panelId = $panel?->getId() ?? 'admin';
            $myProfileRouteName = 'filament.'.$panelId.'.pages.'.$breezy->slug();

            $myProfileRouteParameters = [];

            if (filament()->hasTenancy()) {
                if (! $tenantId = request()->route()?->parameter('tenant')) {
                    return $next($request);
                }
                $myProfileRouteParameters = ['tenant' => $tenantId];
                $twoFactorRoute = route('filament.'.$panelId.'.auth.two-factor', ['tenant' => $tenantId, 'next' => request()->getRequestUri()]);
            } else {
                $twoFactorRoute = route('filament.'.$panelId.'.auth.two-factor', ['next' => request()->getRequestUri()]);
            }

            $user = filament()->auth()->user();

            if ($breezy->shouldForceTwoFactor() && ! $request->routeIs($myProfileRouteName)) {
                return redirect()->route($myProfileRouteName, $myProfileRouteParameters);
            } elseif ($user && $user->hasConfirmedTwoFactor() && ! $user->hasValidTwoFactorSession()) {
                return redirect($twoFactorRoute);
            }
        }

        return $next($request);
    }
}
