<?php

declare(strict_types=1);

namespace Aicl\Horizon;

/**
 * @codeCoverageIgnore Horizon process management
 */
class SupervisorFactory
{
    /**
     * Create a new supervisor instance.
     *
     * @return Supervisor
     */
    public function make(SupervisorOptions $options)
    {
        return new Supervisor($options);
    }
}
