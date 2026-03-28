<?php

declare(strict_types=1);

namespace Aicl\Horizon;

use Symfony\Component\Process\Process;

/**
 * SystemProcessCounter.
 */
class SystemProcessCounter
{
    /**
     * The base command to search for.
     *
     * @var string
     */
    public static $command = 'aicl:horizon:work';

    /**
     * Get the number of Horizon workers for a given supervisor.
     *
     * @param  string  $name
     *
     * @codeCoverageIgnore Reason: horizon-process -- Process counting requires /proc or exec access
     *
     * @return int
     */
    public function get($name)
    {
        $process = Process::fromShellCommandline('exec ps aux | grep '.static::$command, null, ['COLUMNS' => '2000']);

        $process->run();

        return substr_count($process->getOutput(), 'supervisor='.$name);
    }
}
