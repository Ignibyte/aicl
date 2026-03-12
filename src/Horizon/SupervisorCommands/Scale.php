<?php

namespace Aicl\Horizon\SupervisorCommands;

use Aicl\Horizon\Supervisor;

class Scale
{
    /**
     * Process the command.
     *
     * @return void
     */
    public function process(Supervisor $supervisor, array $options)
    {
        $supervisor->scale($options['scale']);
    }
}
