<?php

declare(strict_types=1);

namespace Aicl\AI;

use Aicl\AI\Jobs\AiStreamJob;
use Aicl\Services\EntityRegistry;
use Aicl\Traits\HasAiContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/** Handles AI assistant ask/stream endpoints with entity context resolution. */
class AiAssistantController extends Controller
{
    public function ask(AiAssistantRequest $request): JsonResponse
    {
        if (! AiProviderFactory::isConfigured()) {
            return response()->json([
                'error' => 'AI provider not configured. Set the appropriate API key in config/local.php (e.g., aicl.ai.openai.api_key or aicl.ai.anthropic.api_key).',
            ], 422);
        }

        $context = [];

        // Resolve entity context if provided
        if ($request->filled('entity_type') && $request->filled('entity_id')) {
            $context = $this->resolveEntityContext(
                $request->input('entity_type'),
                $request->input('entity_id'),
            );

            if ($context === null) {
                return response()->json([
                    'error' => 'Entity not found or does not support AI context.',
                ], 404);
            }
        }

        // Enforce concurrent stream limit (atomic to prevent TOCTOU race under Swoole)
        $user = $request->user();

        if (! $user) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }

        $userId = $user->id;
        $maxConcurrent = (int) config('aicl.ai.streaming.max_concurrent_per_user', 2);
        $countKey = "ai-stream:user:{$userId}:count";

        // Initialize counter atomically (add returns false if key exists)
        Cache::add($countKey, 0, 300);
        $newCount = Cache::increment($countKey);

        if ($newCount > $maxConcurrent) {
            Cache::decrement($countKey);

            return response()->json([
                'error' => 'Too many concurrent AI streams. Please wait for a current stream to finish.',
            ], 429);
        }

        $streamId = (string) Str::uuid();

        // Store user for channel authorization (auto-expires in 5 minutes)
        Cache::put("ai-stream:{$streamId}:user", $userId, 300);

        AiStreamJob::dispatch(
            streamId: $streamId,
            userId: $userId,
            prompt: $request->input('prompt'),
            systemPrompt: $request->input('system_prompt', config('aicl.ai.system_prompt')),
            context: $context,
        );

        return response()->json([
            'stream_id' => $streamId,
            'channel' => "private-ai.stream.{$streamId}",
        ]);
    }

    /**
     * Resolve entity context via HasAiContext trait.
     *
     * @return array<string, mixed>|null
     */
    private function resolveEntityContext(string $entityType, string $entityId): ?array
    {
        // Allowlist: only registered entity models may be resolved
        $registry = app(EntityRegistry::class);
        $resolvedClass = $registry->resolveType($entityType);

        if ($resolvedClass === null) {
            return null;
        }

        if (! in_array(HasAiContext::class, class_uses_recursive($resolvedClass))) {
            return null;
        }

        $model = $resolvedClass::find($entityId);

        if (! $model) {
            return null;
        }

        // Verify the requesting user can view this specific record
        if (Gate::denies('view', $model)) {
            return null;
        }

        return $model->toAiContext();
    }
}
