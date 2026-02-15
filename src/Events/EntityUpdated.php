<?php

namespace Aicl\Events;

use Aicl\Events\Traits\BroadcastsDomainEvent;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Database\Eloquent\Model;

class EntityUpdated extends DomainEvent implements ShouldBroadcast
{
    use BroadcastsDomainEvent;

    public static string $eventType = 'entity.updated';

    public function __construct(Model $entity)
    {
        parent::__construct();

        $this->forEntity($entity);
    }

    /**
     * @return array<string, mixed>
     */
    public function toPayload(): array
    {
        return [
            'id' => $this->entity->getKey(),
            'type' => class_basename($this->entity),
            'action' => 'updated',
            'changes' => $this->entity->getChanges(),
        ];
    }
}
