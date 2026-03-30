<?php

declare(strict_types=1);

namespace Aicl\Horizon\Contracts;

use Aicl\Horizon\Supervisor;
use stdClass;

/**
 * SupervisorRepository.
 */
interface SupervisorRepository
{
    /**
     * Get the names of all the supervisors currently running.
     *
     * @return array<int, string>
     */
    public function names();

    /**
     * Get information on all of the supervisors.
     *
     * @return array<int, stdClass>
     */
    public function all();

    /**
     * Get information on a supervisor by name.
     *
     * @param string $name
     *
     * @return stdClass|null
     */
    public function find($name);

    /**
     * Get information on the given supervisors.
     *
     * @param array<int, string> $names
     *
     * @return array<int, stdClass>
     */
    public function get(array $names);

    /**
     * Get the longest active timeout setting for a supervisor.
     *
     * @return int
     */
    public function longestActiveTimeout();

    /**
     * Update the information about the given supervisor process.
     */
    public function update(Supervisor $supervisor);

    /**
     * Remove the supervisor information from storage.
     *
     * @param array<int, string>|string $names
     */
    public function forget($names);

    /**
     * Remove expired supervisors from storage.
     */
    public function flushExpired();
}
