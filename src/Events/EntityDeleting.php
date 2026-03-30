<?php

declare(strict_types=1);

namespace Aicl\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired before an AICL entity model is deleted.
 *
 * Dispatched by the HasEntityEvents trait during the Eloquent "deleting" hook.
 * Listeners can perform cleanup or prevent deletion by throwing an exception.
 */
class EntityDeleting
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param Model $entity The entity model about to be deleted
     */
    public function __construct(
        public Model $entity,
    ) {}
}
