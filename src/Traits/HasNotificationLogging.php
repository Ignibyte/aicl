<?php

namespace Aicl\Traits;

use Aicl\Models\NotificationLog;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Add notification log relationship and convenience methods to a User model.
 *
 * @mixin \Illuminate\Database\Eloquent\Model
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
