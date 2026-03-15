<?php

namespace Aicl\Mcp\Concerns;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;

trait ChecksTokenScope
{
    /**
     * Check that the authenticated user's token has the required scope.
     *
     * Returns null if the check passes (user is authenticated and token
     * either has the scope or no token is present). Returns a Response
     * error if the user is unauthenticated or the token lacks the scope.
     */
    protected function checkScope(Request $request, string $scope): ?Response
    {
        $user = $request->user('api');

        if (! $user) {
            return Response::error('Unauthenticated.');
        }

        $token = $user->token();

        if ($token && ! $token->can($scope)) {
            return Response::error("Token lacks '{$scope}' scope.");
        }

        return null;
    }
}
