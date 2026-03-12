<?php

namespace Aicl\Horizon\SupervisorCommands;

use Aicl\Horizon\Contracts\Restartable;

class Restart
{
    /**
     * Process the command.
     *
     * @return void
     */
    public function process(Restartable $restartable)
    {
        $restartable->restart();
    }
}
