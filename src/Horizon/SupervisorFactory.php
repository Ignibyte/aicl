<?php

namespace Aicl\Horizon;

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
