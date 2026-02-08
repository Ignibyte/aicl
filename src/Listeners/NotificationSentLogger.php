<?php

namespace Aicl\Listeners;

use Aicl\Models\NotificationLog;
use Aicl\Notifications\BaseNotification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Events\NotificationSent;

class NotificationSentLogger
{
    /**
     * Log all notifications sent through Laravel's notification system
     * to the notification_logs table for unified audit visibility.
     *
     * Skips AICL BaseNotification instances since those are already
     * logged by NotificationDispatcher.
     */
    public function handle(NotificationSent $event): void
    {
        // Skip AICL notifications — already logged by NotificationDispatcher
        if ($event->notification instanceof BaseNotification) {
            return;
        }

        $notifiable = $event->notifiable;

        if (! $notifiable instanceof Model) {
            return;
        }

        $data = $this->extractData($event);

        NotificationLog::create([
            'type' => get_class($event->notification),
            'notifiable_type' => get_class($notifiable),
            'notifiable_id' => $notifiable->getKey(),
            'channels' => [$event->channel],
            'channel_status' => [$event->channel => 'sent'],
            'data' => $data,
        ]);
    }

    /**
     * Extract readable data from the notification event.
     *
     * @return array<string, mixed>
     */
    protected function extractData(NotificationSent $event): array
    {
        $notification = $event->notification;

        // Filament DatabaseNotification stores data in getDatabaseMessage()
        if (method_exists($notification, 'getDatabaseMessage')) {
            return $notification->getDatabaseMessage();
        }

        // Standard Laravel notification with toArray
        if (method_exists($notification, 'toArray')) {
            return $notification->toArray($event->notifiable);
        }

        return ['type' => get_class($notification)];
    }
}
