<?php

declare(strict_types=1);

namespace Aicl\Notifications\Contracts;

use Aicl\Notifications\Models\NotificationChannel;
use Illuminate\Support\Collection;

/**
 * HasExternalChannels.
 */
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
