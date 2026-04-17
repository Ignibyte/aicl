<?php

declare(strict_types=1);

namespace Aicl\Horizon\Listeners;

use Aicl\Horizon\Lock;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Notification;

/**
 * Queued listener for Horizon's LongWaitDetected event.
 *
 * Runs on a queue worker — not inside the supervisor tick — so a slow
 * SMTP handshake cannot stall the SupervisorLooped loop.
 *
 * @codeCoverageIgnore Horizon process management
 */
class SendNotification implements ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;

    /**
     * Handle the event.
     *
     * @param mixed $event
     */
    public function handle($event)
    {
        $notification = $event->toNotification();

        if (app(Lock::class)->get('notification:'.$notification->signature(), 300) !== true) {
            return;
        }

        // Route to admin email configured in the application
        $adminEmail = config('aicl.notifications.admin_email', config('mail.from.address'));

        if ($adminEmail !== null && $adminEmail !== '') {
            Notification::route('mail', $adminEmail)
                ->notify($notification);
        }
    }
}
