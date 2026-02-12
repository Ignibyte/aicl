<?php

namespace Aicl\Http\Controllers\Api;

use Aicl\Http\Requests\StoreRlmScoreRequest;
use Aicl\Http\Requests\UpdateRlmScoreRequest;
use Aicl\Http\Resources\RlmScoreResource;
use Aicl\Models\RlmScore;
use Aicl\Traits\PaginatesApiRequests;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class RlmScoreController extends Controller
{
    use PaginatesApiRequests;

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', RlmScore::class);

        $query = RlmScore::query()
            ->with('owner')
            ->when($request->query('entity'), fn ($q, $entity) => $q->forEntity($entity))
            ->when($request->query('type'), fn ($q, $type) => $q->ofType($type))
            ->latest()
            ->paginate($this->getPerPage($request));

        return RlmScoreResource::collection($query);
    }

    public function store(StoreRlmScoreRequest $request): JsonResponse
    {
        $record = RlmScore::create([
            ...$request->validated(),
            'owner_id' => $request->user()->id,
        ]);

        return (new RlmScoreResource($record->load('owner')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(RlmScore $record): RlmScoreResource
    {
        Gate::authorize('view', $record);

        return new RlmScoreResource($record->load('owner'));
    }

    public function update(UpdateRlmScoreRequest $request, RlmScore $record): RlmScoreResource
    {
        $record->update($request->validated());

        return new RlmScoreResource($record->fresh('owner'));
    }

    public function destroy(RlmScore $record): JsonResponse
    {
        Gate::authorize('delete', $record);

        $record->delete();

        return response()->json(['message' => 'RlmScore deleted.'], 200);
    }
}
