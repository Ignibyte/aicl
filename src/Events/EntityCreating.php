<?php

namespace Aicl\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EntityCreating
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public Model $entity,
    ) {}
}
