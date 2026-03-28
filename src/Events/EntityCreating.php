<?php

declare(strict_types=1);

namespace Aicl\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired before an AICL entity model is persisted for the first time.
 *
 * Dispatched by the HasEntityEvents trait during the Eloquent "creating" hook.
 * Listeners can inspect or modify the entity before it is saved.
 */
class EntityCreating
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  Model  $entity  The entity model about to be created
     */
    public function __construct(
        public Model $entity,
    ) {}
}
