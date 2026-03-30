<?php

declare(strict_types=1);

namespace Aicl\Horizon\Contracts;

/**
 * HorizonCommandQueue.
 */
interface HorizonCommandQueue
{
    /**
     * Push a command onto a queue.
     *
     * @param string               $name
     * @param string               $command
     * @param array<string, mixed> $options
     */
    public function push($name, $command, array $options = []);

    /**
     * Get the pending commands for a given queue name.
     *
     * @param string $name
     *
     * @return array<int, object>
     */
    public function pending($name);

    /**
     * Flush the command queue for a given queue name.
     *
     * @param string $name
     */
    public function flush($name);
}
