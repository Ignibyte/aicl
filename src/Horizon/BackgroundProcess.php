<?php

declare(strict_types=1);

namespace Aicl\Horizon;

use Symfony\Component\Process\Process;

/**
 * BackgroundProcess.
 */
class BackgroundProcess extends Process
{
    /**
     * Destruct the object.
     *
     * @return void
     */
    public function __destruct()
    {
        //
    }
}
