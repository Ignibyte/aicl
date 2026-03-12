<?php

namespace Aicl\Horizon\SupervisorCommands;

use Aicl\Horizon\Supervisor;

class Balance
{
    /**
     * Process the command.
     *
     * @return void
     */
    public function process(Supervisor $supervisor, array $options)
    {
        $supervisor->balance($options);
    }
}
