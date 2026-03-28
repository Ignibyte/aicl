<?php

declare(strict_types=1);

namespace Aicl\Horizon\Connectors;

use Aicl\Horizon\RedisQueue;
use Illuminate\Queue\Connectors\RedisConnector as BaseConnector;
use Illuminate\Support\Arr;

/**
 * RedisConnector.
 */
class RedisConnector extends BaseConnector
{
    /**
     * Establish a queue connection.
     *
     * @param  array<string, mixed>  $config
     * @return RedisQueue
     */
    public function connect(array $config)
    {
        return new RedisQueue(
            $this->redis, $config['queue'],
            Arr::get($config, 'connection', $this->connection),
            Arr::get($config, 'retry_after', 60),
            Arr::get($config, 'block_for', null),
            Arr::get($config, 'after_commit', null)
        );
    }
}
