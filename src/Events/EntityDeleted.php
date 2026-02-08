<?php

namespace Aicl\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;

class EntityDeleted implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;

    // Note: We intentionally do NOT use SerializesModels here because
    // the model may have already been deleted by the time this event
    // is processed, and SerializesModels would try to re-fetch it.

    /**
     * The ID of the deleted entity.
     */
    public int|string $entityId;

    /**
     * The class name of the deleted entity (e.g., "Project").
     */
    public string $entityType;

    /**
     * The full class name of the deleted entity.
     */
    public string $entityClass;

    public function __construct(Model $entity)
    {
        $this->entityId = $entity->getKey();
        $this->entityType = class_basename($entity);
        $this->entityClass = get_class($entity);
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel('dashboard'),
        ];

        // Add entity-specific channel
        $entityType = strtolower($this->entityType);
        $channels[] = new PrivateChannel("{$entityType}s.{$this->entityId}");

        return $channels;
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->entityId,
            'type' => $this->entityType,
            'action' => 'deleted',
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'entity.deleted';
    }
}
