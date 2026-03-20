<?php

namespace Aicl\Horizon\SupervisorCommands;

use Aicl\Horizon\Supervisor;

class Scale
{
    /**
     * Process the command.
     *
     * @param  array<string, mixed>  $options
     * @return void
     */
    public function process(Supervisor $supervisor, array $options)
    {
        $supervisor->scale($options['scale']);
    }
}
