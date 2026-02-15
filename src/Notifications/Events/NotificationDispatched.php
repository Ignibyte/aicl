<?php

namespace Aicl\Notifications\Events;

use Aicl\Models\NotificationLog;
use Aicl\Notifications\BaseNotification;

class NotificationDispatched
{
    public function __construct(
        public readonly BaseNotification $notification,
        public readonly object $notifiable,
        public readonly NotificationLog $log,
    ) {}
}
