<?php

namespace Aicl\Events\Traits;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;

/**
 * Opt-in broadcasting for DomainEvent subclasses.
 *
 * Add this trait AND implement ShouldBroadcast on your DomainEvent
 * subclass to broadcast it via Reverb/Pusher.
 *
 * Usage:
 *   class IncidentEscalated extends DomainEvent implements ShouldBroadcast
 *   {
 *       use BroadcastsDomainEvent;
 *       ...
 *   }
 */
trait BroadcastsDomainEvent
{
    use InteractsWithSockets;

    /**
     * Get the channels the event should broadcast on.
     *
     * Mirrors the existing EntityCreated pattern: dashboard channel
     * plus an entity-specific channel if an entity is set.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel('dashboard'),
        ];

        $entity = $this->getEntity();
        if ($entity?->exists) {
            $entityType = strtolower(class_basename($entity));
            $channels[] = new PrivateChannel("{$entityType}s.{$entity->getKey()}");
        }

        return $channels;
    }

    /**
     * The event's broadcast name — uses the dot-notation event type.
     */
    public function broadcastAs(): string
    {
        return static::$eventType;
    }

    /**
     * Get the data to broadcast.
     *
     * Combines the event payload with standard domain event metadata.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return array_merge($this->toPayload(), [
            'eventId' => $this->eventId,
            'eventType' => $this->getEventType(),
            'occurredAt' => $this->occurredAt->toIso8601String(),
        ]);
    }
}
