<?php

namespace Aicl\Horizon\SupervisorCommands;

use Aicl\Horizon\Contracts\Pausable;

class ContinueWorking
{
    /**
     * Process the command.
     *
     * @return void
     */
    public function process(Pausable $pausable)
    {
        $pausable->continue();
    }
}
