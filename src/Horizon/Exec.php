<?php

namespace Aicl\Horizon;

class Exec
{
    /**
     * Run the given command.
     *
     * @param  string  $command
     * @return array<int, string>
     */
    public function run($command)
    {
        exec($command, $output);

        return $output;
    }
}
