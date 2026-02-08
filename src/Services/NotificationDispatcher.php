<?php

namespace Aicl\Services;

use Aicl\Models\NotificationLog;
use Aicl\Notifications\BaseNotification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Throwable;

class NotificationDispatcher
{
    /**
     * Send a notification to a single notifiable and log it.
     */
    public function send(
        mixed $notifiable,
        BaseNotification $notification,
        ?Model $sender = null,
    ): NotificationLog {
        $channels = $notification->via($notifiable);

        $log = NotificationLog::create([
            'type' => get_class($notification),
            'notifiable_type' => get_class($notifiable),
            'notifiable_id' => $notifiable->getKey(),
            'sender_type' => $sender ? get_class($sender) : null,
            'sender_id' => $sender?->getKey(),
            'channels' => $channels,
            'channel_status' => $this->initChannelStatus($channels),
            'data' => method_exists($notification, 'toDatabase')
                ? $notification->toDatabase($notifiable)
                : null,
        ]);

        $channelStatus = [];

        foreach ($channels as $channel) {
            try {
                $notifiable->notify(
                    (clone $notification)->onlyVia($channel)
                );
                $channelStatus[$channel] = 'sent';
            } catch (Throwable $e) {
                $channelStatus[$channel] = 'failed';
                report($e);
            }
        }

        $log->update(['channel_status' => $channelStatus]);

        return $log;
    }

    /**
     * Send a notification to many notifiables and log each.
     *
     * @param  Collection<int, Model>  $notifiables
     * @return Collection<int, NotificationLog>
     */
    public function sendToMany(
        Collection $notifiables,
        BaseNotification $notification,
        ?Model $sender = null,
    ): Collection {
        return $notifiables->map(
            fn (Model $notifiable) => $this->send($notifiable, $notification, $sender)
        );
    }

    /**
     * Initialize channel status as pending for all channels.
     *
     * @param  array<int, string>  $channels
     * @return array<string, string>
     */
    protected function initChannelStatus(array $channels): array
    {
        $status = [];

        foreach ($channels as $channel) {
            $status[$channel] = 'pending';
        }

        return $status;
    }
}
