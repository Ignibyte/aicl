<?php

declare(strict_types=1);

namespace Aicl\Horizon\SupervisorCommands;

use Aicl\Horizon\Contracts\Restartable;

/**
 * @codeCoverageIgnore Horizon process management
 */
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
