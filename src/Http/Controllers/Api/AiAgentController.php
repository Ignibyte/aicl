<?php

namespace Aicl\Http\Controllers\Api;

use Aicl\Http\Requests\StoreAiAgentRequest;
use Aicl\Http\Requests\UpdateAiAgentRequest;
use Aicl\Http\Resources\AiAgentResource;
use Aicl\Models\AiAgent;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;

class AiAgentController extends Controller
{
    use AuthorizesRequests;

    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', AiAgent::class);

        $agents = AiAgent::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate();

        return AiAgentResource::collection($agents);
    }

    public function store(StoreAiAgentRequest $request): AiAgentResource
    {
        $this->authorize('create', AiAgent::class);

        $agent = AiAgent::query()->create($request->validated());

        return new AiAgentResource($agent);
    }

    public function show(AiAgent $record): AiAgentResource
    {
        $this->authorize('view', $record);

        return new AiAgentResource($record);
    }

    public function update(UpdateAiAgentRequest $request, AiAgent $record): AiAgentResource
    {
        $this->authorize('update', $record);

        $record->update($request->validated());

        return new AiAgentResource($record->fresh());
    }

    public function destroy(AiAgent $record): JsonResponse
    {
        $this->authorize('delete', $record);

        $record->delete();

        return response()->json(null, 204);
    }
}
