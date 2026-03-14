<?php

use Aicl\Http\Controllers\Api\AiAgentController;
use Aicl\Http\Controllers\Api\AiChatController;
use Aicl\Http\Controllers\Api\AiConversationController;
use Aicl\Http\Controllers\Api\AiMessageController;
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
    Route::apiResource('ai-agents', AiAgentController::class)
        ->parameters(['ai-agents' => 'record']);
    Route::apiResource('ai-conversations', AiConversationController::class)
        ->parameters(['ai-conversations' => 'record']);

    Route::apiResource('ai-conversations.messages', AiMessageController::class)
        ->parameters(['ai-conversations' => 'conversation', 'messages' => 'message'])
        ->only(['index', 'store', 'destroy']);

    Route::post('ai-conversations/{conversation}/chat', [AiChatController::class, 'send'])
        ->name('ai-conversations.chat');
});
