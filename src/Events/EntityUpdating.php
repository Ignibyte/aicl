<?php

declare(strict_types=1);

namespace Aicl\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired before an existing AICL entity model is saved with changes.
 *
 * Dispatched by the HasEntityEvents trait during the Eloquent "updating" hook.
 * Listeners can inspect dirty attributes or modify the entity before persistence.
 *
 * **Synchronous-only.** Listeners for this event MUST NOT implement
 * `ShouldQueue`. The dirty-attribute context is only meaningful during the
 * request lifecycle; queuing would re-fetch a post-save model with no dirty
 * state, making the listener behave unexpectedly.
 */
class EntityUpdating
{
    use Dispatchable;

    /**
     * @param Model $entity The entity model about to be updated
     */
    public function __construct(
        public Model $entity,
    ) {}
}
