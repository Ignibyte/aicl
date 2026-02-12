<?php

namespace Aicl\Http\Controllers\Api;

use Aicl\Http\Requests\StoreRlmFailureRequest;
use Aicl\Http\Requests\UpdateRlmFailureRequest;
use Aicl\Http\Resources\RlmFailureResource;
use Aicl\Models\RlmFailure;
use Aicl\Traits\PaginatesApiRequests;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class RlmFailureController extends Controller
{
    use PaginatesApiRequests;

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', RlmFailure::class);

        $query = RlmFailure::query()
            ->with('owner')
            ->when($request->query('search'), fn ($q, $term) => $q->search($term))
            ->latest()
            ->paginate($this->getPerPage($request));

        return RlmFailureResource::collection($query);
    }

    public function store(StoreRlmFailureRequest $request): JsonResponse
    {
        $record = RlmFailure::create([
            ...$request->validated(),
            'owner_id' => $request->user()->id,
        ]);

        return (new RlmFailureResource($record->load('owner')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(RlmFailure $record): RlmFailureResource
    {
        Gate::authorize('view', $record);

        return new RlmFailureResource($record->load('owner'));
    }

    public function update(UpdateRlmFailureRequest $request, RlmFailure $record): RlmFailureResource
    {
        $record->update($request->validated());

        return new RlmFailureResource($record->fresh('owner'));
    }

    public function destroy(RlmFailure $record): JsonResponse
    {
        Gate::authorize('delete', $record);

        $record->delete();

        return response()->json(['message' => 'RlmFailure deleted.'], 200);
    }

    /**
     * Upsert a failure by failure_code.
     *
     * Creates a new failure if the failure_code does not exist,
     * or updates the existing one. Used by sync --push to avoid
     * duplicate failure records across projects.
     */
    public function upsert(Request $request): JsonResponse
    {
        Gate::authorize('create', RlmFailure::class);

        $validated = $request->validate([
            'failure_code' => ['required', 'string', 'max:255'],
            'pattern_id' => ['nullable', 'string', 'max:255'],
            'category' => ['required', 'string'],
            'subcategory' => ['nullable', 'string', 'max:255'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'root_cause' => ['nullable', 'string'],
            'fix' => ['nullable', 'string'],
            'preventive_rule' => ['nullable', 'string'],
            'severity' => ['required', 'string'],
            'entity_context' => ['nullable', 'array'],
            'scaffolding_fixed' => ['boolean'],
            'aicl_version' => ['nullable', 'string', 'max:255'],
            'laravel_version' => ['nullable', 'string', 'max:255'],
            'project_hash' => ['nullable', 'string', 'max:64'],
        ]);

        $existing = RlmFailure::where('failure_code', $validated['failure_code'])->first();

        if ($existing) {
            $existing->update(collect($validated)->except('failure_code')->toArray());
            $existing->increment('report_count');

            return (new RlmFailureResource($existing->fresh('owner')))
                ->response()
                ->setStatusCode(200);
        }

        $record = RlmFailure::create([
            ...$validated,
            'owner_id' => $request->user()->id,
            'report_count' => 1,
            'project_count' => 1,
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);

        return (new RlmFailureResource($record->load('owner')))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Get the top N most-reported failures.
     */
    public function top(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', RlmFailure::class);

        $limit = min((int) $request->query('limit', 10), 50);

        $failures = RlmFailure::query()
            ->with('owner')
            ->where('report_count', '>', 0)
            ->orderByDesc('report_count')
            ->limit($limit)
            ->get();

        return RlmFailureResource::collection($failures);
    }
}
