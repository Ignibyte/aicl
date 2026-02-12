<?php

namespace Aicl\Http\Controllers\Api;

use Aicl\Http\Requests\StoreRlmLessonRequest;
use Aicl\Http\Requests\UpdateRlmLessonRequest;
use Aicl\Http\Resources\RlmLessonResource;
use Aicl\Models\RlmLesson;
use Aicl\Traits\PaginatesApiRequests;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class RlmLessonController extends Controller
{
    use PaginatesApiRequests;

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', RlmLesson::class);

        $query = RlmLesson::query()
            ->with('owner')
            ->when($request->query('search'), fn ($q, $term) => $q->search($term))
            ->latest()
            ->paginate($this->getPerPage($request));

        return RlmLessonResource::collection($query);
    }

    public function store(StoreRlmLessonRequest $request): JsonResponse
    {
        $record = RlmLesson::create([
            ...$request->validated(),
            'owner_id' => $request->user()->id,
        ]);

        return (new RlmLessonResource($record->load('owner')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(RlmLesson $record): RlmLessonResource
    {
        Gate::authorize('view', $record);

        return new RlmLessonResource($record->load('owner'));
    }

    public function update(UpdateRlmLessonRequest $request, RlmLesson $record): RlmLessonResource
    {
        $record->update($request->validated());

        return new RlmLessonResource($record->fresh('owner'));
    }

    public function destroy(RlmLesson $record): JsonResponse
    {
        Gate::authorize('delete', $record);

        $record->delete();

        return response()->json(['message' => 'RlmLesson deleted.'], 200);
    }

    /**
     * Full-text search across lesson summary, detail, and tags.
     */
    public function search(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', RlmLesson::class);

        $query = $request->query('q', '');

        if (empty($query)) {
            return RlmLessonResource::collection(
                RlmLesson::query()->with('owner')->latest()->paginate($this->getPerPage($request))
            );
        }

        $lessons = RlmLesson::query()
            ->with('owner')
            ->where(function ($q) use ($query): void {
                $term = '%'.mb_strtolower($query).'%';
                $q->whereRaw('LOWER(summary) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(detail) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(tags) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(topic) LIKE ?', [$term]);
            })
            ->latest()
            ->paginate($this->getPerPage($request));

        return RlmLessonResource::collection($lessons);
    }
}
