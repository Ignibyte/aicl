<?php

namespace Aicl\Http\Controllers\Api;

use Aicl\Http\Requests\StoreRlmPatternRequest;
use Aicl\Http\Requests\UpdateRlmPatternRequest;
use Aicl\Http\Resources\RlmPatternResource;
use Aicl\Models\RlmPattern;
use Aicl\Traits\PaginatesApiRequests;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class RlmPatternController extends Controller
{
    use PaginatesApiRequests;

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', RlmPattern::class);

        $query = RlmPattern::query()
            ->with('owner')
            ->when($request->query('search'), fn ($q, $term) => $q->search($term))
            ->latest()
            ->paginate($this->getPerPage($request));

        return RlmPatternResource::collection($query);
    }

    public function store(StoreRlmPatternRequest $request): JsonResponse
    {
        $record = RlmPattern::create([
            ...$request->validated(),
            'owner_id' => $request->user()->id,
        ]);

        return (new RlmPatternResource($record->load('owner')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(RlmPattern $record): RlmPatternResource
    {
        Gate::authorize('view', $record);

        return new RlmPatternResource($record->load('owner'));
    }

    public function update(UpdateRlmPatternRequest $request, RlmPattern $record): RlmPatternResource
    {
        $record->update($request->validated());

        return new RlmPatternResource($record->fresh('owner'));
    }

    public function destroy(RlmPattern $record): JsonResponse
    {
        Gate::authorize('delete', $record);

        $record->delete();

        return response()->json(['message' => 'RlmPattern deleted.'], 200);
    }
}
