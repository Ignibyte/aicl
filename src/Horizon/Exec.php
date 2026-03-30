<?php

declare(strict_types=1);

namespace Aicl\Horizon;

/**
 * Exec.
 */
class Exec
{
    /**
     * Run the given command.
     *
     * @param string $command
     *
     * @return array<int, string>
     */
    public function run($command)
    {
        exec($command, $output);

        return $output;
    }
}
