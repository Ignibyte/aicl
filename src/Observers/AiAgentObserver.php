<?php

declare(strict_types=1);

namespace Aicl\Observers;

use Aicl\Models\AiAgent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * AiAgentObserver.
 */
class AiAgentObserver extends BaseObserver
{
    /**
     * Auto-generate slug from name on creation.
     */
    public function creating(Model $model): void
    {
        /** @var AiAgent $model */
        if (empty($model->slug)) {
            $model->slug = Str::slug($model->name);
        }
    }
}
