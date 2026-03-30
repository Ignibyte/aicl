<?php

declare(strict_types=1);

namespace Aicl\Horizon\Contracts;

use Aicl\Horizon\MasterSupervisor;
use stdClass;

/**
 * MasterSupervisorRepository.
 */
interface MasterSupervisorRepository
{
    /**
     * Get the names of all the master supervisors currently running.
     *
     * @return array<int, string>
     */
    public function names();

    /**
     * Get information on all of the master supervisors.
     *
     * @return array<int, stdClass>
     */
    public function all();

    /**
     * Get information on a master supervisor by name.
     *
     * @param string $name
     *
     * @return stdClass|null
     */
    public function find($name);

    /**
     * Get information on the given master supervisors.
     *
     * @param array<int, string> $names
     *
     * @return array<int, stdClass>
     */
    public function get(array $names);

    /**
     * Update the information about the given master supervisor.
     */
    public function update(MasterSupervisor $master);

    /**
     * Remove the master supervisor information from storage.
     *
     * @param string $name
     */
    public function forget($name);

    /**
     * Remove expired master supervisors from storage.
     */
    public function flushExpired();
}
