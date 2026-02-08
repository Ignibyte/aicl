<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| AICL Package API Routes
|--------------------------------------------------------------------------
|
| Entity-specific API routes are registered by the client application
| after running aicl:make-entity. The package provides no demo routes.
|
*/

Route::middleware(['api', 'auth:api', 'throttle:api'])->prefix('api/v1')->group(function (): void {
    //
});
