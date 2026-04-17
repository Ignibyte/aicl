<?php

declare(strict_types=1);

namespace Aicl\Mcp\Concerns;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;

/**
 * ChecksTokenScope.
 */
trait ChecksTokenScope
{
    /**
     * Check that the authenticated user's token has the required scope.
     *
     * Returns null if the check passes (user is authenticated and the
     * token — if present — carries the required scope). Returns a
     * Response error if the user is unauthenticated or the token lacks
     * the scope.
     *
     * Session-authenticated users (no Passport token present) pass through
     * here because the `/mcp` HTTP route already enforces a 4-layer defense
     * at the boundary (`api` + `auth:api` + `throttle:api` + `CheckToken::using('mcp')`)
     * — a real HTTP request cannot reach this trait without a valid
     * Passport access token carrying the `mcp` scope. The session-auth
     * fallthrough is therefore only reachable from unit-test invocations,
     * console commands, and admin UI code paths that resolve via session
     * auth. None of those are externally attacker-reachable.
     *
     * Fail-closed was analyzed during the v2.1.0 self-reflection review and
     * closed as **wontfix** — the audit finding was a pattern match against
     * the permissive `if ($token !== null && ...)` construct, but the
     * architectural 4-layer defense stack makes the attack surface zero.
     * See `docs/planning/brainstorm/BRAINSTORM-63-ChecksTokenScope-FailClosed.md`
     * for the full analysis.
     */
    protected function checkScope(Request $request, string $scope): ?Response
    {
        $user = $request->user('api');

        if ($user === null) {
            return Response::error('Unauthenticated.');
        }

        $token = $user->token();

        if ($token !== null && ! $token->can($scope)) {
            return Response::error("Token lacks '{$scope}' scope.");
        }

        return null;
    }
}
