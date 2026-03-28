<?php

declare(strict_types=1);

namespace Aicl\Horizon;

/**
 * WorkerCommandString.
 */
class WorkerCommandString
{
    /**
     * The base worker command.
     *
     * @var string
     */
    public static $command = 'exec @php artisan aicl:horizon:work';

    /**
     * Get the command-line representation of the options for a worker.
     *
     * @return string
     */
    public static function fromOptions(SupervisorOptions $options)
    {
        $command = str_replace('@php', PhpBinary::path(), static::$command);

        return sprintf(
            "%s {$options->connection} %s",
            $command,
            static::toOptionsString($options)
        );
    }

    /**
     * Get the additional option string for the command.
     *
     * @return string
     */
    public static function toOptionsString(SupervisorOptions $options)
    {
        return QueueCommandString::toWorkerOptionsString($options);
    }

    /**
     * Reset the base command back to its default value.
     *
     * @return void
     */
    public static function reset()
    {
        static::$command = 'exec @php artisan aicl:horizon:work';
    }
}
