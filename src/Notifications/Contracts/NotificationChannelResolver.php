<?php

declare(strict_types=1);

namespace Aicl\Notifications\Contracts;

use Aicl\Notifications\BaseNotification;
use Aicl\Notifications\Models\NotificationChannel;
use Illuminate\Support\Collection;

/**
 * NotificationChannelResolver.
 */
interface NotificationChannelResolver
{
    /**
     * Resolve which external notification channels should receive this notification.
     *
     * @return Collection<int, NotificationChannel>
     */
    public function resolve(BaseNotification $notification, object $notifiable): Collection;
}
