<?php

declare(strict_types=1);

use Aicl\AI\AiAssistantController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| AICL Package Web Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['web'])->group(function () {
    Route::post('ai/ask', [AiAssistantController::class, 'ask'])
        ->middleware(['auth', 'throttle:ai_assistant'])
        ->name('api.v1.ai.ask');

    // Fallback login route for API/OAuth/MCP auth middleware.
    // Laravel's Authenticate middleware redirects to the named 'login' route
    // when unauthenticated. Without this, API requests get an Ignition error
    // page instead of a proper 401 response.
    if (! Route::has('login')) {
        Route::get('login', fn () => response()->json(['message' => 'Unauthenticated.'], 401))
            ->name('login');
    }
});
