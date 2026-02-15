<?php

namespace Aicl\Notifications\Events;

use Aicl\Notifications\BaseNotification;
use Illuminate\Database\Eloquent\Model;

class NotificationSending
{
    public bool $cancelled = false;

    public function __construct(
        public readonly BaseNotification $notification,
        public readonly object $notifiable,
        public readonly ?Model $sender = null,
    ) {}

    /**
     * Cancel this notification — it will not be sent.
     */
    public function cancel(): void
    {
        $this->cancelled = true;
    }
}
