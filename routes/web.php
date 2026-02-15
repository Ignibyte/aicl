<?php

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
});
