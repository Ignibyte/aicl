<?php

declare(strict_types=1);

namespace Aicl\Horizon\Events;

use Aicl\Horizon\Contracts\LongWaitDetectedNotification;
use Illuminate\Container\Container;

/**
 * LongWaitDetected.
 */
class LongWaitDetected
{
    /**
     * The queue connection name.
     *
     * @var string
     */
    public $connection;

    /**
     * The queue name.
     *
     * @var string
     */
    public $queue;

    /**
     * The wait time in seconds.
     *
     * @var int
     */
    public $seconds;

    /**
     * Create a new event instance.
     *
     * @param  string  $connection
     * @param  string  $queue
     * @param  int  $seconds
     * @return void
     */
    public function __construct($connection, $queue, $seconds)
    {
        $this->queue = $queue;
        $this->seconds = $seconds;
        $this->connection = $connection;
    }

    /**
     * Get a notification representation of the event.
     *
     * @return \Aicl\Horizon\Notifications\LongWaitDetected
     */
    public function toNotification()
    {
        // @codeCoverageIgnoreStart — Horizon process management
        return Container::getInstance()->make(LongWaitDetectedNotification::class, [
            'connection' => $this->connection,
            'queue' => $this->queue,
            'seconds' => $this->seconds,
        ]);
        // @codeCoverageIgnoreEnd
    }
}
