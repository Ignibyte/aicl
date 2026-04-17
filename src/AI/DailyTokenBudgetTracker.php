<?php

declare(strict_types=1);

namespace Aicl\AI;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Canonical owner of the per-user daily AI token budget cache.
 *
 * Backs `AiMessageObserver::created()` (writes) and
 * `AiChatService::enforceDailyTokenBudget()` (reads). Keeps the cache key
 * shape and TTL invariants in one place instead of duplicated across the
 * observer and the service.
 *
 * **Cache key:** `ai:user:{userId}:daily_tokens:{YYYY-MM-DD}` with 86,400-
 * second TTL seeded via `Cache::add($key, 0, 86400)` on first write of the
 * day. Matches architecture decision `security.ai-rate-limit-keys` byte-for-
 * byte — this service is a pure extraction, not a key migration.
 *
 * **Stateless.** No mutable instance state. All per-user state lives in the
 * cache keyed by user_id. Constructor is parameterless. Octane-safe by
 * construction (verified via a reflection-based invariant in the unit test).
 *
 * **Exception contract.** Tracker methods throw raw cache exceptions — they
 * do NOT swallow. The caller (`AiChatService::enforceDailyTokenBudget`) wraps
 * the call in the existing fail-closed try/catch per architecture decision
 * `security.ai-rate-limit-fail-closed`. This keeps fail-mode policy in one
 * place (the enforcement boundary) and leaves the tracker as a pure data-
 * access service.
 *
 * **No-refund policy.** `refund()` exists as API surface for operator-invoked
 * admin corrections (e.g. manual credit after a provider incident). It is NOT
 * called from any automatic code path — daily budget is a cost CEILING, not a
 * token ledger. See architecture decision `security.ai-budget-no-refund-policy`.
 */
class DailyTokenBudgetTracker
{
    /**
     * Key TTL in seconds (24 hours).
     *
     * Preserves the TTL from the pre-extraction inline logic at
     * `AiMessageObserver::incrementDailyTokenBudget()` prior to Pipeline G.
     */
    private const int CACHE_TTL_SECONDS = 86_400;

    /**
     * Record token usage against the user's daily counter.
     *
     * No-op if `$tokenCount <= 0`, `$userId <= 0`, or the daily budget
     * feature is disabled (`token_budget_daily <= 0`). The budget-off
     * short-circuit preserves the zero-overhead semantics from the pre-
     * extraction inline logic — no point writing data no enforcement
     * path will read.
     *
     * First write of the day seeds the key with `Cache::add($key, 0, TTL)`;
     * subsequent increments preserve the TTL via Redis `INCRBY` semantics.
     *
     * @param int $userId     The user whose daily counter to increment
     * @param int $tokenCount Non-negative token count to add (no-op on zero)
     */
    public function record(int $userId, int $tokenCount): void
    {
        if ($tokenCount <= 0 || $userId <= 0) {
            return;
        }

        if ((int) config('aicl.ai.assistant.token_budget_daily') <= 0) {
            return;
        }

        $key = $this->keyFor($userId);

        Cache::add($key, 0, self::CACHE_TTL_SECONDS);
        Cache::increment($key, $tokenCount);
    }

    /**
     * Refund token usage against the user's daily counter, flooring at zero.
     *
     * **API surface for operator-invoked admin corrections only** — NOT
     * called by `AiMessageObserver::deleted()`. Daily budget is a cost
     * ceiling; automatic refund on message delete would let a user exhaust
     * quota, delete, and retry indefinitely. See architecture decision
     * `security.ai-budget-no-refund-policy`.
     *
     * @param int $userId     The user whose daily counter to decrement
     * @param int $tokenCount Non-negative token count to subtract (no-op on zero)
     */
    public function refund(int $userId, int $tokenCount): void
    {
        if ($tokenCount <= 0 || $userId <= 0) {
            return;
        }

        $key = $this->keyFor($userId);
        $newValue = Cache::decrement($key, $tokenCount);

        if (is_numeric($newValue) && (int) $newValue < 0) {
            // Normalize negative drivers (some cache backends allow negative
            // values on decrement). Preserve remaining TTL via `Cache::put` —
            // `Cache::add` would be a no-op since the key still exists.
            Cache::put($key, 0, self::CACHE_TTL_SECONDS);
        }
    }

    /**
     * Read the current daily usage for a user. Returns 0 when the key is
     * unset (fresh day, or feature never used by this user).
     *
     * @param int $userId The user whose daily usage to read
     *
     * @return int Current token usage for today (≥ 0)
     */
    public function currentUsage(int $userId): int
    {
        if ($userId <= 0) {
            return 0;
        }

        return (int) Cache::get($this->keyFor($userId), 0);
    }

    /**
     * Evaluate whether the user's current usage meets or exceeds the
     * configured daily budget.
     *
     * Returns `false` when `token_budget_daily <= 0` — feature is disabled,
     * no enforcement should occur. Returns `true` when `currentUsage >= budget`
     * (at-ceiling is already over; matches the pre-extraction `$used >= $budget`
     * semantics in `AiChatService::enforceDailyTokenBudget`).
     */
    public function wouldExceedBudget(int $userId): bool
    {
        $budget = (int) config('aicl.ai.assistant.token_budget_daily');

        if ($budget <= 0) {
            return false;
        }

        return $this->currentUsage($userId) >= $budget;
    }

    /**
     * Build the day-scoped cache key for a user.
     *
     * Key shape: `ai:user:{userId}:daily_tokens:{YYYY-MM-DD}`. Matches
     * `security.ai-rate-limit-keys` byte-for-byte.
     */
    private function keyFor(int $userId): string
    {
        $today = Carbon::now()->format('Y-m-d');

        return "ai:user:{$userId}:daily_tokens:{$today}";
    }
}
