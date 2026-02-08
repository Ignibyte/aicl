<?php

namespace Aicl\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

abstract class BaseNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * When set, restricts delivery to only this channel.
     * Used by NotificationDispatcher for per-channel dispatch.
     */
    protected ?string $onlyChannel = null;

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        if ($this->onlyChannel) {
            return [$this->onlyChannel];
        }

        return ['database', 'mail', 'broadcast'];
    }

    /**
     * Restrict this notification to a single channel.
     * Used by NotificationDispatcher for per-channel dispatch and logging.
     */
    public function onlyVia(string $channel): static
    {
        $this->onlyChannel = $channel;

        return $this;
    }

    /**
     * Get the array representation of the notification for database storage.
     *
     * @return array<string, mixed>
     */
    abstract public function toDatabase(object $notifiable): array;

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $data = $this->toDatabase($notifiable);

        return (new MailMessage)
            ->subject($data['title'] ?? 'New Notification')
            ->line($data['body'] ?? '')
            ->when(
                isset($data['action_url']),
                fn (MailMessage $mail) => $mail->action(
                    $data['action_text'] ?? 'View Details',
                    $data['action_url']
                )
            );
    }

    /**
     * Get the broadcast representation of the notification.
     * Triggers Filament's built-in Echo listener for instant bell updates.
     */
    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toDatabase($notifiable));
    }

    /**
     * Get the notification's icon.
     */
    public function getIcon(): string
    {
        return 'heroicon-o-bell';
    }

    /**
     * Get the notification's color.
     */
    public function getColor(): string
    {
        return 'primary';
    }
}
