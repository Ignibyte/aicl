<?php

declare(strict_types=1);

namespace Aicl\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired before an existing AICL entity model is saved with changes.
 *
 * Dispatched by the HasEntityEvents trait during the Eloquent "updating" hook.
 * Listeners can inspect dirty attributes or modify the entity before persistence.
 */
class EntityUpdating
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  Model  $entity  The entity model about to be updated
     */
    public function __construct(
        public Model $entity,
    ) {}
}
