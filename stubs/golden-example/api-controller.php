<?php

// PATTERN: API Controller uses Form Requests for validation (never inline).
// PATTERN: Uses Gate::authorize() for authorization (Laravel 11 — no AuthorizesRequests trait).
// PATTERN: Returns Eloquent API Resources (not arrays).
// PATTERN: Uses eager loading with ->with() to prevent N+1.

namespace Aicl\Http\Controllers\Api;

use Aicl\Http\Requests\StoreProjectRequest;
use Aicl\Http\Requests\UpdateProjectRequest;
use Aicl\Http\Resources\ProjectResource;
use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class ProjectController extends Controller
{
    // PATTERN: Index with filtering, search, pagination, and eager loading.
    public function index(Request $request): AnonymousResourceCollection
    {
        $projects = Project::query()
            ->with('owner')
            ->withCount('members')
            // PATTERN: Optional query filters via when().
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->query('priority'), fn ($q, $priority) => $q->where('priority', $priority))
            // PATTERN: Use the search() scope from HasStandardScopes.
            ->when($request->query('search'), fn ($q, $term) => $q->search($term))
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return ProjectResource::collection($projects);
    }

    // PATTERN: Store uses Form Request (authorization + validation in one class).
    public function store(StoreProjectRequest $request): ProjectResource
    {
        $project = Project::create([
            ...$request->validated(),
            // PATTERN: Set owner to authenticated user.
            'owner_id' => $request->user()->id,
        ]);

        // PATTERN: Handle pivot relationships if provided.
        if ($request->has('member_ids')) {
            $project->members()->attach($request->input('member_ids'));
        }

        $project->load('owner');

        return new ProjectResource($project);
    }

    // PATTERN: Show with Gate::authorize() and eager loading.
    public function show(Project $project): ProjectResource
    {
        Gate::authorize('view', $project);

        $project->load(['owner', 'members']);
        $project->loadCount('members');

        return new ProjectResource($project);
    }

    // PATTERN: Update uses separate Form Request class.
    public function update(UpdateProjectRequest $request, Project $project): ProjectResource
    {
        $project->update($request->validated());

        // PATTERN: sync() for many-to-many on update (not attach).
        if ($request->has('member_ids')) {
            $project->members()->sync($request->input('member_ids'));
        }

        $project->load('owner');

        return new ProjectResource($project);
    }

    // PATTERN: Destroy with Gate::authorize() and soft delete.
    public function destroy(Project $project): JsonResponse
    {
        Gate::authorize('delete', $project);

        $project->delete();

        return response()->json(['message' => 'Project deleted.'], 200);
    }
}
