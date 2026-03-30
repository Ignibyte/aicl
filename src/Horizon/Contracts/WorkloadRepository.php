<?php

declare(strict_types=1);

namespace Aicl\Horizon\Contracts;

/**
 * WorkloadRepository.
 */
interface WorkloadRepository
{
    /**
     * Get the current workload of each queue.
     *
     * @return array<int, array{"name": string, "length": int, "wait": int, "processes": int, "split_queues": array<int, array{"name": string, "wait": int, "length": int}>|null}>
     */
    public function get();
}
