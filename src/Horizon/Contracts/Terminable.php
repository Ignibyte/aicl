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
     * @param int $status
     */
    public function terminate($status = 0);
}
