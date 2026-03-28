<?php

declare(strict_types=1);

namespace Aicl\Horizon\Contracts;

/**
 * Terminable.
 */
interface Terminable
{
    /**
     * Terminate the process.
     *
     * @param  int  $status
     * @return void
     */
    public function terminate($status = 0);
}
