<?php

namespace Aicl\Observers;

use Aicl\Models\AiConversation;
use Aicl\Models\AiMessage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class AiMessageObserver extends BaseObserver
{
    /**
     * After creating a message, update the conversation's counters in a single query.
     *
     * Consolidates message_count increment, token_count increment, and
     * last_message_at update into one UPDATE statement instead of 2-3 separate queries.
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
                'token_count' => DB::raw("token_count + {$tokenCount}"),
                'last_message_at' => $model->created_at,
            ]);
    }

    /**
     * After deleting a message, decrement conversation counters in a single query.
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
                'token_count' => DB::raw("GREATEST(token_count - {$tokenCount}, 0)"),
            ]);
    }
}
