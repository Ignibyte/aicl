<?php

namespace Aicl\Http\Controllers\Api;

use Aicl\Http\Requests\StoreRlmFailureRequest;
use Aicl\Http\Requests\UpdateRlmFailureRequest;
use Aicl\Http\Requests\UpsertRlmFailureRequest;
use Aicl\Http\Resources\RlmFailureResource;
use Aicl\Models\RlmFailure;
use Aicl\Repositories\RlmFailureRepository;
use Aicl\Traits\PaginatesApiRequests;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class RlmFailureController extends Controller
{
    use PaginatesApiRequests;

    public function __construct(
        private RlmFailureRepository $failureRepository,
    ) {}

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
    public function upsert(UpsertRlmFailureRequest $request): JsonResponse
    {
        $result = $this->failureRepository->upsertByCode(
            $request->validated(),
            $request->user()->id,
        );

        return (new RlmFailureResource($result['record']->load('owner')))
            ->response()
            ->setStatusCode($result['created'] ? 201 : 200);
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
