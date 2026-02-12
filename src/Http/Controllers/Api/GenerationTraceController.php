<?php

namespace Aicl\Http\Controllers\Api;

use Aicl\Http\Requests\StoreGenerationTraceRequest;
use Aicl\Http\Requests\UpdateGenerationTraceRequest;
use Aicl\Http\Resources\GenerationTraceResource;
use Aicl\Models\GenerationTrace;
use Aicl\Traits\PaginatesApiRequests;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class GenerationTraceController extends Controller
{
    use PaginatesApiRequests;

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', GenerationTrace::class);

        $query = GenerationTrace::query()
            ->with('owner')
            ->when($request->query('search'), fn ($q, $term) => $q->search($term))
            ->latest()
            ->paginate($this->getPerPage($request));

        return GenerationTraceResource::collection($query);
    }

    public function store(StoreGenerationTraceRequest $request): JsonResponse
    {
        $record = GenerationTrace::create([
            ...$request->validated(),
            'owner_id' => $request->user()->id,
        ]);

        return (new GenerationTraceResource($record->load('owner')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(GenerationTrace $record): GenerationTraceResource
    {
        Gate::authorize('view', $record);

        return new GenerationTraceResource($record->load('owner'));
    }

    public function update(UpdateGenerationTraceRequest $request, GenerationTrace $record): GenerationTraceResource
    {
        $record->update($request->validated());

        return new GenerationTraceResource($record->fresh('owner'));
    }

    public function destroy(GenerationTrace $record): JsonResponse
    {
        Gate::authorize('delete', $record);

        $record->delete();

        return response()->json(['message' => 'GenerationTrace deleted.'], 200);
    }
}
