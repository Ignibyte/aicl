<?php

declare(strict_types=1);

namespace Aicl\Broadcasting;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

/**
 * Abstract base class for domain broadcast events.
 *
 * Provides a consistent structure for real-time WebSocket events broadcast
 * via Laravel Reverb. Each event has a unique ID, dot-notation type, and
 * ISO 8601 timestamp. Broadcasts on a private 'dashboard' channel by default,
 * and optionally on an entity-specific channel if getEntity() returns a model.
 *
 * Subclasses must define eventType() and may override toPayload() and getEntity().
 *
 * @see ChannelAuth  Channel authorization for private channels
 *
 * @codeCoverageIgnore Broadcasting infrastructure
 */
abstract class BaseBroadcastEvent implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public readonly string $eventId;

    public readonly string $eventType;

    public readonly string $occurredAt;

    public function __construct()
    {
        $this->eventId = (string) Str::uuid();
        $this->eventType = static::eventType();
        $this->occurredAt = now()->toIso8601String();
    }

    /**
     * Dot-notation event type. Subclasses MUST define.
     */
    abstract public static function eventType(): string;

    /**
     * Business payload. Subclasses override to provide data.
     *
     * @return array<string, mixed>
     */
    public function toPayload(): array
    {
        return [];
    }

    /**
     * Optional entity to derive entity-specific channel.
     */
    public function getEntity(): ?Model
    {
        return null;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        $channels = [new PrivateChannel('dashboard')];

        $entity = $this->getEntity();

        if ($entity !== null) {
            if ($entity->exists) {
                $type = strtolower(class_basename($entity));
                $channels[] = new PrivateChannel("{$type}s.{$entity->getKey()}");
            }
        }

        return $channels;
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return $this->eventType;
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return array_merge($this->toPayload(), [
            'eventId' => $this->eventId,
            'eventType' => $this->eventType,
            'occurredAt' => $this->occurredAt,
        ]);
    }
}
