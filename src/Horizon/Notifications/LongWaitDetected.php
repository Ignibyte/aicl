<?php

declare(strict_types=1);

namespace Aicl\Horizon\Notifications;

use Aicl\Horizon\Contracts\LongWaitDetectedNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * LongWaitDetected.
 */
class LongWaitDetected extends Notification implements LongWaitDetectedNotification
{
    use Queueable;

    /**
     * The queue connection name.
     *
     * @var string
     */
    public $longWaitConnection;

    /**
     * The queue name.
     *
     * @var string
     */
    public $longWaitQueue;

    /**
     * The wait time in seconds.
     *
     * @var int
     */
    public $seconds;

    /**
     * Create a new notification instance.
     *
     * @param string $connection
     * @param string $queue
     * @param int    $seconds
     */
    public function __construct($connection, $queue, $seconds)
    {
        $this->longWaitQueue = $queue;
        $this->seconds = $seconds;
        $this->longWaitConnection = $connection;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param mixed $notifiable
     *
     * @return array<int, string>
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function via($notifiable)
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param mixed $notifiable
     *
     * @return MailMessage
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function toMail($notifiable)
    {
        // @codeCoverageIgnoreStart — Horizon process management
        return (new MailMessage)
            ->error()
            ->subject(config('app.name').': Long Queue Wait Detected')
            ->greeting('Queue alert — action required.')
            ->line(sprintf(
                'The "%s" queue on the "%s" connection has a wait time of %s seconds.',
                $this->longWaitQueue, $this->longWaitConnection, $this->seconds
            ));
        // @codeCoverageIgnoreEnd
    }

    /**
     * The unique signature of the notification.
     *
     * @return string
     */
    public function signature()
    {
        return md5($this->longWaitConnection.$this->longWaitQueue);
    }
}
