<?php

namespace Aicl\Http\Controllers\Api;

use Aicl\Http\Resources\AiMessageResource;
use Aicl\Models\AiConversation;
use Aicl\Models\AiMessage;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;

class AiMessageController extends Controller
{
    use AuthorizesRequests;

    public function index(AiConversation $conversation): AnonymousResourceCollection
    {
        $this->authorize('view', $conversation);

        $messages = $conversation->messages()
            ->orderBy('created_at')
            ->paginate();

        return AiMessageResource::collection($messages);
    }

    public function store(Request $request, AiConversation $conversation): AiMessageResource
    {
        $this->authorize('update', $conversation);

        $validated = $request->validate([
            'role' => ['required', 'string', 'in:user'],
            'content' => ['required', 'string', 'max:2000'],
            'token_count' => ['nullable', 'integer', 'min:0'],
        ]);

        $message = $conversation->messages()->create($validated);

        return new AiMessageResource($message);
    }

    public function destroy(AiConversation $conversation, AiMessage $message): JsonResponse
    {
        $this->authorize('update', $conversation);

        abort_unless($message->ai_conversation_id === $conversation->id, 404);

        $message->delete();

        return response()->json(null, 204);
    }
}
