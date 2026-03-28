<?php

declare(strict_types=1);

namespace Aicl\Horizon\Contracts;

/**
 * ProcessRepository.
 */
interface ProcessRepository
{
    /**
     * Get all of the orphan process IDs and the times they were observed.
     *
     * @param  string  $master
     * @return array<string, string>
     */
    public function allOrphans($master);

    /**
     * Record the given process IDs as orphaned.
     *
     * @param  string  $master
     * @param  array<int, string>  $processIds
     * @return void
     */
    public function orphaned($master, array $processIds);

    /**
     * Get the process IDs orphaned for at least the given number of seconds.
     *
     * @param  string  $master
     * @param  int  $seconds
     * @return array<int, string>
     */
    public function orphanedFor($master, $seconds);

    /**
     * Remove the given process IDs from the orphan list.
     *
     * @param  string  $master
     * @param  array<int, string>  $processIds
     * @return void
     */
    public function forgetOrphans($master, array $processIds);
}
