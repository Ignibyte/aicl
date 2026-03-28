<?php

declare(strict_types=1);

namespace Aicl\Observers;

use Aicl\Models\AiConversation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * AiConversationObserver.
 */
class AiConversationObserver extends BaseObserver
{
    /**
     * Auto-generate title from a default if not provided.
     */
    public function creating(Model $model): void
    {
        /** @var AiConversation $model */
        if (empty($model->title)) {
            $model->title = 'New Conversation — '.Str::substr(now()->toDateTimeString(), 0, 16);
        }
    }
}
