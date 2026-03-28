<?php

declare(strict_types=1);

namespace Aicl\Broadcasting\Traits;

/**
 * @codeCoverageIgnore Broadcasting infrastructure
 */
trait HasPresenceChannel
{
    /**
     * Get the presence channel name for this model.
     */
    public function presenceChannelName(): string
    {
        $type = strtolower(class_basename(static::class));

        return "presence.{$type}s.{$this->getKey()}";
    }

    /**
     * Get the authorization permission for viewing this entity's presence.
     * Default: ViewAny:{Entity} (Shield format).
     */
    public function presencePermission(): string
    {
        return 'ViewAny:'.class_basename(static::class);
    }
}
