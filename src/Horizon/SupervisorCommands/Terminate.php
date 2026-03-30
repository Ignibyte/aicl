<?php

declare(strict_types=1);

namespace Aicl\Horizon\SupervisorCommands;

use Aicl\Horizon\Contracts\Terminable;

/**
 * @codeCoverageIgnore Horizon process management
 */
class Terminate
{
    /**
     * Process the command.
     *
     * @param array<string, mixed> $options
     */
    public function process(Terminable $terminable, array $options)
    {
        $terminable->terminate($options['status'] ?? 0);
    }
}
