<?php

declare(strict_types=1);

namespace Aicl\Notifications\Events;

use Aicl\Models\NotificationLog;
use Aicl\Notifications\BaseNotification;

/**
 * @codeCoverageIgnore Notification infrastructure
 */
class NotificationDispatched
{
    public function __construct(
        public readonly BaseNotification $notification,
        public readonly object $notifiable,
        public readonly NotificationLog $log,
    ) {}
}
