<?php

use Aicl\Mcp\AiclMcpServer;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;
use Laravel\Passport\Http\Middleware\CheckToken;

$mcpPath = config('aicl.mcp.path', '/mcp');
$middleware = config('aicl.mcp.middleware', ['api', 'auth:api', 'throttle:api']);

// Append Passport scope middleware to ensure the token has the 'mcp' scope.
// Tokens with '*' (full access) pass all scope checks automatically.
$middleware[] = CheckToken::using('mcp');

Route::middleware($middleware)->group(function () use ($mcpPath): void {
    Mcp::web($mcpPath, AiclMcpServer::class);
});

// Register OAuth well-known endpoints for MCP client auto-discovery
Mcp::oauthRoutes();
