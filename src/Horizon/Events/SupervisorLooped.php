<?php

declare(strict_types=1);

namespace Aicl\Horizon\Events;

use Aicl\Horizon\Supervisor;

/**
 * SupervisorLooped.
 */
class SupervisorLooped
{
    /**
     * The supervisor instance.
     *
     * @var Supervisor
     */
    public $supervisor;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(Supervisor $supervisor)
    {
        $this->supervisor = $supervisor;
    }
}
