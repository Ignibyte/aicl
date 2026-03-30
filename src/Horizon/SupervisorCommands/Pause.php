<?php

declare(strict_types=1);

namespace Aicl\Horizon\SupervisorCommands;

use Aicl\Horizon\Contracts\Pausable;

/**
 * @codeCoverageIgnore Horizon process management
 */
class Pause
{
    /**
     * Process the command.
     */
    public function process(Pausable $pausable)
    {
        $pausable->pause();
    }
}
