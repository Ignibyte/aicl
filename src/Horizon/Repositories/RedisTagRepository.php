<?php

declare(strict_types=1);

namespace Aicl\Horizon\Repositories;

use Aicl\Horizon\Contracts\TagRepository;
use Illuminate\Contracts\Redis\Factory;
use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Illuminate\Redis\Connections\Connection;

/**
 * Redis-backed tag repository for Horizon job tagging.
 */
class RedisTagRepository implements TagRepository
{
    /**
     * The Redis connection instance.
     *
     * @var Factory
     */
    public $redis;

    /**
     * Create a new repository instance.
     */
    public function __construct(RedisFactory $redis)
    {
        $this->redis = $redis;
    }

    /**
     * Get the currently monitored tags.
     *
     * @return array<int, string>
     */
    public function monitoring()
    {
        return (array) $this->connection()->smembers('monitoring');
    }

    /**
     * Return the tags which are being monitored.
     *
     * @param array<int, string> $tags
     *
     * @return array<int, string>
     */
    public function monitored(array $tags)
    {
        // @codeCoverageIgnoreStart — Horizon process management
        return array_intersect($tags, $this->monitoring());
        // @codeCoverageIgnoreEnd
    }

    /**
     * Start monitoring the given tag.
     *
     * @param string $tag
     */
    public function monitor($tag)
    {
        // @codeCoverageIgnoreStart — Horizon process management
        $this->connection()->sadd('monitoring', $tag);
        // @codeCoverageIgnoreEnd
    }

    /**
     * Stop monitoring the given tag.
     *
     * @param string $tag
     */
    public function stopMonitoring($tag)
    {
        // @codeCoverageIgnoreStart — Horizon process management
        $this->connection()->srem('monitoring', $tag);
        // @codeCoverageIgnoreEnd
    }

    /**
     * Store the tags for the given job.
     *
     * @param string             $id
     * @param array<int, string> $tags
     */
    public function add($id, array $tags)
    {
        // @codeCoverageIgnoreStart — Horizon process management
        $this->connection()->pipeline(function ($pipe) use ($id, $tags) {
            foreach ($tags as $tag) {
                $pipe->zadd($tag, str_replace(',', '.', (string) microtime(true)), $id);
            }
        });
        // @codeCoverageIgnoreEnd
    }

    /**
     * Store the tags for the given job temporarily.
     *
     * @param int                $minutes
     * @param string             $id
     * @param array<int, string> $tags
     */
    public function addTemporary($minutes, $id, array $tags)
    {
        // @codeCoverageIgnoreStart — Horizon process management
        $this->connection()->pipeline(function ($pipe) use ($minutes, $id, $tags) {
            foreach ($tags as $tag) {
                $pipe->zadd($tag, str_replace(',', '.', (string) microtime(true)), $id);

                $pipe->expire($tag, $minutes * 60);
            }
        });
        // @codeCoverageIgnoreEnd
    }

    /**
     * Get the number of jobs matching a given tag.
     *
     * @param string $tag
     *
     * @return int
     */
    public function count($tag)
    {
        // @codeCoverageIgnoreStart — Horizon process management
        return $this->connection()->zcard($tag);
        // @codeCoverageIgnoreEnd
    }

    /**
     * Get all of the job IDs for a given tag.
     *
     * @param string $tag
     *
     * @return array<int, string>
     */
    public function jobs($tag)
    {
        // @codeCoverageIgnoreStart — Horizon process management
        return (array) $this->connection()->zrange($tag, 0, -1);
        // @codeCoverageIgnoreEnd
    }

    /**
     * Paginate the job IDs for a given tag.
     *
     * @param string $tag
     * @param int    $startingAt
     * @param int    $limit
     *
     * @return array<int, string>
     */
    public function paginate($tag, $startingAt = 0, $limit = 25)
    {
        // @codeCoverageIgnoreStart — Horizon process management
        $tags = (array) $this->connection()->zrevrange(
            $tag, $startingAt, $startingAt + $limit - 1
        );

        return collect($tags)
            ->values()
            ->mapWithKeys(fn ($tag, $index) => [$index + $startingAt => $tag])
            ->all();
        // @codeCoverageIgnoreEnd
    }

    /**
     * Remove the given job IDs from the given tag.
     *
     * @param array<int, string>|string $tags
     * @param array<int, string>|string $ids
     */
    public function forgetJobs($tags, $ids)
    {
        // @codeCoverageIgnoreStart — Horizon process management
        $this->connection()->pipeline(function ($pipe) use ($tags, $ids) {
            foreach ((array) $tags as $tag) {
                foreach ((array) $ids as $id) {
                    $pipe->zrem($tag, $id);
                }
            }
        });
        // @codeCoverageIgnoreEnd
    }

    /**
     * Delete the given tag from storage.
     *
     * @param string $tag
     */
    public function forget($tag)
    {
        // @codeCoverageIgnoreStart — Horizon process management
        $this->connection()->del($tag);
        // @codeCoverageIgnoreEnd
    }

    /**
     * Get the Redis connection instance.
     *
     * @return Connection
     */
    protected function connection()
    {
        return $this->redis->connection('horizon');
    }
}
