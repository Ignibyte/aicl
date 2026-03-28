<?php

declare(strict_types=1);

namespace Aicl\Horizon\Contracts;

/**
 * Pausable.
 */
interface Pausable
{
    /**
     * Pause the process.
     *
     * @return void
     */
    public function pause();

    /**
     * Instruct the process to continue working.
     *
     * @return void
     */
    public function continue();
}
