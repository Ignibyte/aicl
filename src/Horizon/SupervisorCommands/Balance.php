<?php

declare(strict_types=1);

namespace Aicl\Horizon\SupervisorCommands;

use Aicl\Horizon\Supervisor;

/**
 * @codeCoverageIgnore Horizon process management
 */
class Balance
{
    /**
     * Process the command.
     *
     * @param array<string, int|float> $options
     */
    public function process(Supervisor $supervisor, array $options)
    {
        $supervisor->balance($options);
    }
}
