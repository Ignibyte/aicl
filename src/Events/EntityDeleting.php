<?php

declare(strict_types=1);

namespace Aicl\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired before an AICL entity model is deleted.
 *
 * Dispatched by the HasEntityEvents trait during the Eloquent "deleting" hook.
 * Listeners can perform cleanup based on the scalar identity fields.
 *
 * Stores entity identity as scalar properties rather than the live Model
 * reference to prevent `SerializesModels` re-hydration failures if a listener
 * queues — the row may already be gone by the time the queue worker tries to
 * re-fetch. Mirrors the scalar-override pattern used by EntityDeleted.
 *
 * @see EntityDeleted The sibling "after delete" event using the same pattern
 */
class EntityDeleting
{
    use Dispatchable;

    /**
     * The ID of the entity about to be deleted.
     */
    public int|string $entityId;

    /**
     * The short class name of the entity (e.g., "Project").
     */
    public string $entityType;

    /**
     * The fully-qualified class name of the entity.
     *
     * @var class-string<Model>
     */
    public string $entityClass;

    public function __construct(Model $entity)
    {
        $this->entityId = $entity->getKey();
        $this->entityType = class_basename($entity);
        $this->entityClass = $entity::class;
    }
}
