<?php

namespace Aicl\Events;

use Aicl\Events\Traits\BroadcastsDomainEvent;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Database\Eloquent\Model;

class EntityDeleted extends DomainEvent implements ShouldBroadcast
{
    use BroadcastsDomainEvent;

    public static string $eventType = 'entity.deleted';

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
        parent::__construct();

        $this->entityId = $entity->getKey();
        $this->entityType = class_basename($entity);
        $this->entityClass = get_class($entity);

        // Don't call forEntity() — the model may be deleted by the time
        // SerializesModels tries to re-fetch it. Use scalar overrides instead.
    }

    /**
     * Override entity type for DomainEvent persistence using scalar value.
     */
    public function getEntityType(): ?string
    {
        return $this->entityClass;
    }

    /**
     * Override entity ID for DomainEvent persistence using scalar value.
     */
    public function getEntityId(): int|string|null
    {
        return $this->entityId;
    }

    /**
     * Override broadcast channels to use scalar properties since
     * the model may no longer exist after deletion.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        $entityType = strtolower($this->entityType);

        return [
            new PrivateChannel('dashboard'),
            new PrivateChannel("{$entityType}s.{$this->entityId}"),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toPayload(): array
    {
        return [
            'id' => $this->entityId,
            'type' => $this->entityType,
            'action' => 'deleted',
        ];
    }
}
