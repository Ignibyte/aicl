<?php

declare(strict_types=1);

namespace Aicl\Horizon\Contracts;

/**
 * Restartable.
 */
interface Restartable
{
    /**
     * Restart the process.
     *
     * @return void
     */
    public function restart();
}
