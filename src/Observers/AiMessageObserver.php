<?php

namespace Aicl\Observers;

use Aicl\Models\AiMessage;
use Illuminate\Database\Eloquent\Model;

class AiMessageObserver extends BaseObserver
{
    /**
     * After creating a message, update the conversation's counters.
     */
    public function created(Model $model): void
    {
        /** @var AiMessage $model */
        $conversation = $model->conversation;

        if ($conversation) {
            $conversation->increment('message_count');

            if ($model->token_count) {
                $conversation->increment('token_count', $model->token_count);
            }

            $conversation->update(['last_message_at' => $model->created_at]);
        }
    }

    /**
     * After deleting a message, decrement conversation counters.
     */
    public function deleted(Model $model): void
    {
        /** @var AiMessage $model */
        $conversation = $model->conversation;

        if ($conversation) {
            $conversation->decrement('message_count');

            if ($model->token_count) {
                $conversation->decrement('token_count', $model->token_count);
            }
        }
    }
}
