<?php

declare(strict_types=1);

namespace Aicl\Observers;

use Aicl\AI\DailyTokenBudgetTracker;
use Aicl\Models\AiConversation;
use Aicl\Models\AiMessage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * AiMessageObserver.
 *
 * Owns two aggregates on AiMessage lifecycle events:
 *   (1) the conversation DB counters (message_count, token_count, last_message_at)
 *   (2) the per-user daily token budget cache (delegated to `DailyTokenBudgetTracker`)
 *
 * Prior to Pipeline G both aggregates lived inline on this class, mixing DB
 * writes with cache writes. The daily-budget path has been extracted to a
 * dedicated service per architecture decision `security.ai-budget-tracker-service`;
 * the DB counter path remains here.
 */
class AiMessageObserver extends BaseObserver
{
    /**
     * Lazy-initialized tracker. Constructor-less backward-compat per
     * `refactoring.service-extraction-backward-compat-lazy-init` — existing
     * tests that do `new AiMessageObserver()` directly continue to work.
     */
    private ?DailyTokenBudgetTracker $tracker = null;

    /**
     * After creating a message, update the conversation's counters in a single query
     * and record the message's tokens against the user's daily budget.
     *
     * The DB update consolidates message_count increment, token_count increment, and
     * last_message_at update into one UPDATE statement instead of 2-3 separate
     * queries. The daily budget cache write is delegated to `DailyTokenBudgetTracker`
     * which owns the key shape and TTL.
     */
    public function created(Model $model): void
    {
        /** @var AiMessage $model */
        if (! $model->ai_conversation_id) {
            return;
        }

        $tokenCount = (int) $model->token_count;

        AiConversation::query()
            ->where('id', $model->ai_conversation_id)
            ->update([
                'message_count' => DB::raw('message_count + 1'),
                // $tokenCount is explicitly (int)-cast above — interpolation is safe.
                // Concat (not templated into a SQL string) so the numeric type is
                // unambiguous at the call site; matches the deleted() hook pattern.
                'token_count' => DB::raw('token_count + '.$tokenCount),
                'last_message_at' => $model->created_at,
            ]);

        $userId = (int) ($model->conversation?->user_id ?? 0);
        $this->tracker()->record($userId, $tokenCount);
    }

    /**
     * After deleting a message, decrement the conversation's DB counters.
     *
     * **Intentionally does NOT refund the daily budget counter.** Daily budget
     * is a cost ceiling, not a ledger — tokens sent to the provider cost money
     * at the API level regardless of whether the message is later deleted from
     * our DB. Refunding would let a user exhaust quota, delete, and retry
     * indefinitely. See architecture decision `security.ai-budget-no-refund-policy`.
     * `DailyTokenBudgetTracker::refund()` exists as API surface for operator-
     * invoked admin corrections but is NOT wired into this automatic path.
     */
    public function deleted(Model $model): void
    {
        /** @var AiMessage $model */
        if (! $model->ai_conversation_id) {
            return;
        }

        $tokenCount = (int) $model->token_count;

        AiConversation::query()
            ->where('id', $model->ai_conversation_id)
            ->update([
                'message_count' => DB::raw('GREATEST(message_count - 1, 0)'),
                // $tokenCount is (int)-cast above — concat (not templated) makes
                // the numeric type unambiguous at the call site.
                'token_count' => DB::raw('GREATEST(token_count - '.$tokenCount.', 0)'),
            ]);
    }

    /**
     * Resolve the tracker lazily so `new AiMessageObserver()` still works
     * from existing tests that don't use the container.
     */
    protected function tracker(): DailyTokenBudgetTracker
    {
        return $this->tracker ??= app(DailyTokenBudgetTracker::class);
    }
}
