<?php

declare(strict_types=1);

namespace Aicl\Http\Controllers\Api;

use Aicl\Http\Requests\StoreAiConversationRequest;
use Aicl\Http\Requests\UpdateAiConversationRequest;
use Aicl\Http\Resources\AiConversationResource;
use Aicl\Models\AiConversation;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;

/** API controller for AI conversation CRUD and message sending. */
class AiConversationController extends Controller
{
    use AuthorizesRequests;

    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', AiConversation::class);

        $user = auth()->user();

        if (! $user) {
            abort(401);
        }

        $conversations = AiConversation::query()
            ->with(['user', 'agent'])
            ->forUser($user)
            ->recent()
            ->paginate();

        return AiConversationResource::collection($conversations);
    }

    public function store(StoreAiConversationRequest $request): AiConversationResource
    {
        $this->authorize('create', AiConversation::class);

        $conversation = AiConversation::query()->create([
            ...$request->validated(),
            'user_id' => auth()->id(),
        ]);

        return new AiConversationResource($conversation->load(['user', 'agent']));
    }

    public function show(AiConversation $record): AiConversationResource
    {
        $this->authorize('view', $record);

        return new AiConversationResource($record->load(['user', 'agent']));
    }

    public function update(UpdateAiConversationRequest $request, AiConversation $record): AiConversationResource
    {
        $this->authorize('update', $record);

        $record->update($request->validated());

        return new AiConversationResource($record->refresh()->load(['user', 'agent']));
    }

    public function destroy(AiConversation $record): JsonResponse
    {
        $this->authorize('delete', $record);

        $record->delete();

        return response()->json(null, 204);
    }
}
