<?php

declare(strict_types=1);

namespace Aicl\AI;

use Aicl\AI\Exceptions\AiRateLimitException;
use Aicl\AI\Jobs\AiConversationStreamJob;
use Aicl\Enums\AiMessageRole;
use Aicl\Models\AiAgent;
use Aicl\Models\AiConversation;
use Aicl\Models\AiMessage;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use NeuronAI\Chat\Enums\MessageRole;
use NeuronAI\Chat\Messages\Message;
use RuntimeException;
use Throwable;

/**
 * Service for managing AI assistant chat conversations.
 *
 * Handles message sending (with concurrent stream limiting), authorization
 * checks against agent role restrictions, and building the message history
 * context window for AI providers. Conversations are streamed via WebSocket
 * through Reverb using AiConversationStreamJob.
 *
 * @see AiConversation  Conversation model
 * @see AiAgent  AI agent configuration model
 * @see AiConversationStreamJob  Job that streams AI responses
 */
class AiChatService
{
    /**
     * Lazy-initialized daily token budget tracker. Constructor-less backward-
     * compat per `refactoring.service-extraction-backward-compat-lazy-init`:
     * existing tests that do `new AiChatService()` directly keep working.
     */
    private ?DailyTokenBudgetTracker $tracker = null;

    /**
     * Verify that a user has role-based access to a conversation's agent.
     *
     * @throws RuntimeException If the user does not have access
     */
    public function authorizeAccess(AiConversation $conversation, ?User $user): void
    {
        $agent = $conversation->agent;

        if (! $agent) {
            throw new RuntimeException('AI agent not found.');
        }

        if (! $agent->isAccessibleByUser($user)) {
            throw new RuntimeException('You do not have access to this AI agent.');
        }
    }

    /**
     * Send a user message to a conversation and trigger AI streaming.
     *
     * Creates the user's AiMessage, enforces concurrent stream limits,
     * and dispatches the conversation stream job.
     *
     * @throws RuntimeException If the agent is not available or concurrent limit exceeded
     *
     * @return array{stream_id: string, channel: string, message_id: string}
     */
    public function sendMessage(AiConversation $conversation, string $content, ?User $user = null): array
    {
        $agent = $conversation->agent;

        if (! $agent || ! $agent->is_active || ! $agent->is_configured) {
            throw new RuntimeException('AI agent is not available or not configured.');
        }

        // Enforce role-based access
        if ($user && ! $agent->isAccessibleByUser($user)) {
            throw new RuntimeException('You do not have access to this AI agent.');
        }

        // Enforce per-agent per-user RPM limit + per-user daily token budget
        // (single source of truth for both Livewire and HTTP paths).
        if ($user !== null) {
            $this->enforcePerAgentRpmLimit($agent, $user);
            $this->enforceDailyTokenBudget($user);
        }

        // Create user message
        $userMessage = $conversation->messages()->create([
            'role' => AiMessageRole::User,
            'content' => $content,
        ]);

        // Generate stream ID
        $streamId = (string) Str::uuid();
        $userId = $conversation->user_id;

        // Store user for channel authorization (auto-expires in 5 minutes)
        Cache::put("ai-stream:{$streamId}:user", $userId, 300);

        // Enforce concurrent stream limit (atomic increment to prevent TOCTOU race)
        $maxConcurrent = (int) config('aicl.ai.streaming.max_concurrent_per_user', 2);
        $countKey = "ai-stream:user:{$userId}:count";

        // Initialize key if missing (increment requires existing key on some drivers)
        Cache::add($countKey, 0, 300);
        $newCount = (int) Cache::increment($countKey);

        if ($newCount > $maxConcurrent) {
            Cache::decrement($countKey);

            throw new RuntimeException('Too many concurrent AI streams. Please wait for a current stream to finish.');
        }

        // Dispatch conversation stream job
        AiConversationStreamJob::dispatch(
            streamId: $streamId,
            conversationId: $conversation->id,
            userId: $userId,
        );

        return [
            'stream_id' => $streamId,
            'channel' => "private-ai.stream.{$streamId}",
            'message_id' => $userMessage->id,
        ];
    }

    /**
     * Enforce the per-agent per-user requests-per-minute ceiling.
     *
     * Uses the atomic hit-first-then-check pattern: `RateLimiter::hit()`
     * returns the post-increment count via Redis INCR (single-command atomic),
     * and the comparison happens against that returned value — eliminating
     * the TOCTOU window that existed with `tooManyAttempts + hit`. Skips when
     * the agent has no `max_requests_per_minute` configured.
     *
     * Fails-CLOSED on cache outage: if `RateLimiter::hit()` throws (Redis
     * unreachable), the user sees an `AiRateLimitException` with a 60-second
     * retry hint rather than a silent bypass.
     *
     * @throws AiRateLimitException When the user has exceeded the RPM ceiling
     *                              or the rate-limit cache is unreachable
     */
    protected function enforcePerAgentRpmLimit(AiAgent $agent, User $user): void
    {
        $rpm = (int) $agent->max_requests_per_minute;

        if ($rpm <= 0) {
            return;
        }

        $rpmKey = "ai:agent:{$agent->id}:user:{$user->id}:rpm";

        try {
            $hits = RateLimiter::hit($rpmKey, 60);
        } catch (Throwable $e) {
            Log::error('AI rate-limit cache unreachable', [
                'agent_id' => $agent->id,
                'user_id' => $user->id,
                'exception' => $e->getMessage(),
            ]);
            $this->emitMetric('cache_outage', $user, $agent);

            throw new AiRateLimitException(
                'AI rate-limit service temporarily unavailable. Try again shortly.',
                60,
            );
        }

        if ($hits > $rpm) {
            $retryAfter = $this->safeAvailableIn($rpmKey);
            $this->emitMetric('rpm', $user, $agent);

            throw new AiRateLimitException(
                "Rate limit reached for this agent. Try again in {$retryAfter} seconds.",
                $retryAfter,
            );
        }
    }

    /**
     * Enforce the per-user daily token budget.
     *
     * Delegates the cache read to `DailyTokenBudgetTracker::wouldExceedBudget`
     * per architecture decision `security.ai-budget-tracker-service`. The
     * counter itself is maintained by `AiMessageObserver::created` (also via
     * the tracker). Skips when no daily budget is configured.
     *
     * Fails-CLOSED on cache outage: if the tracker throws (Redis unreachable),
     * this method surfaces an `AiRateLimitException` with a 60-second retry
     * hint rather than silently bypassing the control — per architecture
     * decision `security.ai-rate-limit-fail-closed`.
     *
     * Shadow mode: when `aicl.ai.assistant.token_budget_daily_warn_only = true`
     * AND the user is at-or-over budget, this method logs + increments the
     * `budget_warn_only` metric but does NOT throw. Enables operators to dark-
     * launch a budget + observe metrics before committing to enforcement. See
     * architecture decision `feature.ai-budget-shadow-mode`.
     *
     * @throws AiRateLimitException When the user has hit the daily ceiling
     *                              (and warn_only is false) or the budget
     *                              cache is unreachable
     */
    protected function enforceDailyTokenBudget(User $user): void
    {
        $budget = (int) config('aicl.ai.assistant.token_budget_daily');

        if ($budget <= 0) {
            return;
        }

        try {
            $exceeds = $this->tracker()->wouldExceedBudget((int) $user->id);
        } catch (Throwable $e) {
            Log::error('AI budget cache unreachable', [
                'user_id' => $user->id,
                'exception' => $e->getMessage(),
            ]);
            $this->emitMetric('cache_outage', $user);

            throw new AiRateLimitException(
                'AI budget temporarily unavailable. Try again shortly.',
                60,
            );
        }

        if (! $exceeds) {
            return;
        }

        $warnOnly = (bool) config('aicl.ai.assistant.token_budget_daily_warn_only', false);

        if ($warnOnly) {
            Log::warning('ai.rate_limit.hit', [
                'kind' => 'budget_warn_only',
                'user_id' => $user->id,
            ]);
            $this->emitMetric('budget_warn_only', $user);

            return;
        }

        Log::warning('ai.rate_limit.hit', [
            'kind' => 'budget',
            'user_id' => $user->id,
        ]);
        $this->emitMetric('budget', $user);

        throw new AiRateLimitException(
            'Daily token budget exceeded. Budget resets at midnight UTC.',
            86400,
        );
    }

    /**
     * Lazily resolve the daily token budget tracker.
     *
     * Lazy-init preserves backward compatibility for tests that construct
     * `new AiChatService()` directly (e.g. `AiChatServiceEnforcementTest`).
     * See lesson `refactoring.service-extraction-backward-compat-lazy-init`.
     */
    protected function tracker(): DailyTokenBudgetTracker
    {
        return $this->tracker ??= app(DailyTokenBudgetTracker::class);
    }

    /**
     * Emit a structured operator signal for rate-limit / budget events.
     *
     * Writes to `metrics:ai_rate_limit:{kind}:{YmdH}` (48-hour TTL) in the
     * `metrics:` namespace, disjoint from enforcement state. Metrics emission
     * is best-effort — a failed counter increment MUST NOT break the user's
     * request, so the whole method is wrapped in a swallowing catch. See
     * architecture decision `security.ai-metrics-namespace`.
     *
     * @param string $kind One of: rpm, budget, budget_warn_only, cache_outage
     */
    protected function emitMetric(string $kind, User $user, ?AiAgent $agent = null): void
    {
        try {
            $window = Carbon::now()->format('YmdH');
            $key = "metrics:ai_rate_limit:{$kind}:{$window}";

            Cache::add($key, 0, 172_800);
            Cache::increment($key);

            Log::warning('ai.rate_limit.hit', [
                'kind' => $kind,
                'user_id' => $user->id,
                'agent_id' => $agent?->id,
            ]);
        } catch (Throwable) {
            // Metrics are best-effort; never propagate telemetry failures
            // up the call stack — that would break the user request for a
            // non-critical side effect.
        }
    }

    /**
     * Read the rate-limiter retry hint with a safe fallback.
     *
     * A secondary Redis flap between `hit()` and `availableIn()` would
     * otherwise produce an unhelpful exception. Falls back to the default
     * 60-second window if `availableIn()` throws.
     */
    protected function safeAvailableIn(string $rpmKey): int
    {
        try {
            return (int) RateLimiter::availableIn($rpmKey);
        } catch (Throwable) {
            return 60;
        }
    }

    /**
     * Build the message history for a conversation.
     *
     * Respects the agent's context_messages limit.
     * Includes conversation summary as system context if available.
     *
     * @return array<int, Message>
     */
    public function buildMessageHistory(AiConversation $conversation): array
    {
        $agent = $conversation->agent;
        $limit = $agent->context_messages ?? 20;

        $messages = [];

        // If conversation has been compacted, include summary as system context
        if ($conversation->summary) {
            $messages[] = new Message(
                MessageRole::SYSTEM,
                "Previous conversation summary:\n{$conversation->summary}",
            );
        }

        // Load recent messages in chronological order using a subquery to avoid
        // the inefficient orderByDesc→reverse pattern that loads all rows into PHP
        $recentMessages = AiMessage::query()
            ->whereIn('id', function ($query) use ($conversation, $limit): void {
                $query->select('id')
                    ->from('ai_messages')
                    ->where('ai_conversation_id', $conversation->id)
                    ->orderByDesc('created_at')
                    ->limit($limit);
            })
            ->orderBy('created_at')
            ->get();

        foreach ($recentMessages as $msg) {
            /** @var AiMessage $msg */
            $role = match ($msg->role) {
                AiMessageRole::User => MessageRole::USER,
                AiMessageRole::Assistant => MessageRole::ASSISTANT,
                AiMessageRole::System => MessageRole::SYSTEM,
            };

            $messages[] = new Message($role, $msg->content);
        }

        return $messages;
    }
}
