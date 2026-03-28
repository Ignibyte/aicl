<?php

declare(strict_types=1);

namespace Aicl\Http\Controllers\Api;

use Aicl\AI\AiChatService;
use Aicl\Http\Requests\AiChatRequest;
use Aicl\Models\AiConversation;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

/**
 * AiChatController.
 */
class AiChatController extends Controller
{
    use AuthorizesRequests;

    public function send(AiChatRequest $request, AiConversation $conversation, AiChatService $chatService): JsonResponse
    {
        $this->authorize('update', $conversation);

        try {
            $result = $chatService->sendMessage(
                $conversation,
                $request->input('message'),
                $request->user(),
            );

            return response()->json($result);
        } catch (\RuntimeException $e) {
            $statusCode = str_contains($e->getMessage(), 'concurrent') ? 429 : 422;

            return response()->json([
                'error' => $e->getMessage(),
            ], $statusCode);
        }
    }
}
