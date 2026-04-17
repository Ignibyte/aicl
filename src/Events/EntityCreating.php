<?php

declare(strict_types=1);

namespace Aicl\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired before an AICL entity model is persisted for the first time.
 *
 * Dispatched by the HasEntityEvents trait during the Eloquent "creating" hook.
 * Listeners can inspect or modify the entity before it is saved.
 *
 * **Synchronous-only.** Listeners for this event MUST NOT implement
 * `ShouldQueue`. The entity has not yet been persisted — it has no primary
 * key, and `SerializesModels` serialization would fail at the queue boundary.
 * Any pre-save inspection or mutation must happen inline during request handling.
 */
class EntityCreating
{
    use Dispatchable;

    /**
     * @param Model $entity The entity model about to be created
     */
    public function __construct(
        public Model $entity,
    ) {}
}
