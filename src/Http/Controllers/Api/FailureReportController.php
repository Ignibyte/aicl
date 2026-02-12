<?php

namespace Aicl\Http\Controllers\Api;

use Aicl\Http\Requests\StoreFailureReportRequest;
use Aicl\Http\Requests\UpdateFailureReportRequest;
use Aicl\Http\Resources\FailureReportResource;
use Aicl\Models\FailureReport;
use Aicl\Traits\PaginatesApiRequests;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class FailureReportController extends Controller
{
    use PaginatesApiRequests;

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', FailureReport::class);

        $query = FailureReport::query()
            ->with(['owner', 'failure'])
            ->when($request->query('search'), fn ($q, $term) => $q->search($term))
            ->latest('reported_at')
            ->paginate($this->getPerPage($request));

        return FailureReportResource::collection($query);
    }

    public function store(StoreFailureReportRequest $request): JsonResponse
    {
        $record = FailureReport::create([
            ...$request->validated(),
            'owner_id' => $request->user()->id,
        ]);

        return (new FailureReportResource($record->load(['owner', 'failure'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(FailureReport $record): FailureReportResource
    {
        Gate::authorize('view', $record);

        return new FailureReportResource($record->load(['owner', 'failure']));
    }

    public function update(UpdateFailureReportRequest $request, FailureReport $record): FailureReportResource
    {
        $record->update($request->validated());

        return new FailureReportResource($record->fresh(['owner', 'failure']));
    }

    public function destroy(FailureReport $record): JsonResponse
    {
        Gate::authorize('delete', $record);

        $record->delete();

        return response()->json(['message' => 'FailureReport deleted.'], 200);
    }
}
