<?php

namespace Aicl\Horizon\Listeners;

use Aicl\Horizon\Lock;
use Illuminate\Support\Facades\Notification;

class SendNotification
{
    /**
     * Handle the event.
     *
     * @param  mixed  $event
     * @return void
     */
    public function handle($event)
    {
        $notification = $event->toNotification();

        if (! app(Lock::class)->get('notification:'.$notification->signature(), 300)) {
            return;
        }

        // Route to admin email configured in the application
        $adminEmail = config('aicl.notifications.admin_email', config('mail.from.address'));

        if ($adminEmail) {
            Notification::route('mail', $adminEmail)
                ->notify($notification);
        }
    }
}
