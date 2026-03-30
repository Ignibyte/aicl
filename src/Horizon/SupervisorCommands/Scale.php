<?php

declare(strict_types=1);

namespace Aicl\Horizon\SupervisorCommands;

use Aicl\Horizon\Supervisor;

/**
 * @codeCoverageIgnore Horizon process management
 */
class Scale
{
    /**
     * Process the command.
     *
     * @param array<string, mixed> $options
     */
    public function process(Supervisor $supervisor, array $options)
    {
        $supervisor->scale($options['scale']);
    }
}
