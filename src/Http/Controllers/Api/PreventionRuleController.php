<?php

namespace Aicl\Http\Controllers\Api;

use Aicl\Http\Requests\StorePreventionRuleRequest;
use Aicl\Http\Requests\UpdatePreventionRuleRequest;
use Aicl\Http\Resources\PreventionRuleResource;
use Aicl\Models\PreventionRule;
use Aicl\Traits\PaginatesApiRequests;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class PreventionRuleController extends Controller
{
    use PaginatesApiRequests;

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', PreventionRule::class);

        $query = PreventionRule::query()
            ->with(['owner', 'failure'])
            ->when($request->query('search'), fn ($q, $term) => $q->search($term))
            ->latest()
            ->paginate($this->getPerPage($request));

        return PreventionRuleResource::collection($query);
    }

    public function store(StorePreventionRuleRequest $request): JsonResponse
    {
        $record = PreventionRule::create([
            ...$request->validated(),
            'owner_id' => $request->user()->id,
        ]);

        return (new PreventionRuleResource($record->load(['owner', 'failure'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(PreventionRule $record): PreventionRuleResource
    {
        Gate::authorize('view', $record);

        return new PreventionRuleResource($record->load(['owner', 'failure']));
    }

    public function update(UpdatePreventionRuleRequest $request, PreventionRule $record): PreventionRuleResource
    {
        $record->update($request->validated());

        return new PreventionRuleResource($record->fresh(['owner', 'failure']));
    }

    public function destroy(PreventionRule $record): JsonResponse
    {
        Gate::authorize('delete', $record);

        $record->delete();

        return response()->json(['message' => 'PreventionRule deleted.'], 200);
    }

    /**
     * Query prevention rules by entity context.
     *
     * Accepts query parameters that describe the entity being generated
     * (e.g. has_states=true, field_types=enum,foreignId) and returns
     * matching active prevention rules.
     */
    public function forEntity(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', PreventionRule::class);

        $context = collect($request->query())
            ->except(['page', 'per_page'])
            ->mapWithKeys(function ($value, $key) {
                // Convert comma-separated strings to arrays
                if (is_string($value) && str_contains($value, ',')) {
                    return [$key => explode(',', $value)];
                }

                // Convert string booleans
                if ($value === 'true') {
                    return [$key => true];
                }
                if ($value === 'false') {
                    return [$key => false];
                }

                return [$key => $value];
            })
            ->toArray();

        $rules = PreventionRule::query()
            ->with(['owner', 'failure'])
            ->active()
            ->when(! empty($context), fn ($q) => $q->forContext($context))
            ->orderByDesc('confidence')
            ->orderByDesc('priority')
            ->paginate($this->getPerPage($request));

        return PreventionRuleResource::collection($rules);
    }
}
