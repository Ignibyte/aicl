<?php

namespace Aicl\Horizon\SupervisorCommands;

use Aicl\Horizon\Supervisor;

class Balance
{
    /**
     * Process the command.
     *
     * @param  array<string, int|float>  $options
     * @return void
     */
    public function process(Supervisor $supervisor, array $options)
    {
        $supervisor->balance($options);
    }
}
