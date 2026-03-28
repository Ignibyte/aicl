<?php

declare(strict_types=1);

namespace Aicl\Traits;

use Aicl\Models\NotificationLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Add notification log relationship and convenience methods to a User model.
 *
 * @mixin Model
 *
 * @codeCoverageIgnore Trait requiring integration context
 */
trait HasNotificationLogging
{
    /**
     * @return MorphMany<NotificationLog, $this>
     */
    public function notificationLogs(): MorphMany
    {
        return $this->morphMany(NotificationLog::class, 'notifiable');
    }
}
