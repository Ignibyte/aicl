<?php

namespace Aicl\Horizon\SupervisorCommands;

use Aicl\Horizon\Contracts\Terminable;

class Terminate
{
    /**
     * Process the command.
     *
     * @param  array<string, mixed>  $options
     * @return void
     */
    public function process(Terminable $terminable, array $options)
    {
        $terminable->terminate($options['status'] ?? 0);
    }
}
