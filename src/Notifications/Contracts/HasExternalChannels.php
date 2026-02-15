<?php

namespace Aicl\Notifications\Contracts;

use Aicl\Notifications\Models\NotificationChannel;
use Illuminate\Support\Collection;

interface HasExternalChannels
{
    /**
     * Get the external channels this notification should be sent to.
     * Only used when no NotificationChannelResolver is configured.
     *
     * @return Collection<int, NotificationChannel>
     */
    public function externalChannels(): Collection;
}
