<?php

declare(strict_types=1);

namespace Aicl\Horizon;

use Aicl\Horizon\Contracts\Silenced;
use ArrayAccess;
use Illuminate\Broadcasting\BroadcastEvent;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Mail\SendQueuedMailable;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Arr;
use ReturnTypeWillChange;

/**
 * @implements ArrayAccess<string, mixed>
 */
class JobPayload implements ArrayAccess
{
    /**
     * The raw payload string.
     *
     * @var string
     */
    public $value;

    /**
     * The decoded payload array.
     *
     * @var array<string, mixed>
     */
    public $decoded;

    /**
     * Create a new raw job payload instance.
     *
     * @param string $value
     */
    public function __construct($value)
    {
        $this->value = $value;

        $this->decoded = json_decode($value, true);
    }

    /**
     * Get the job ID from the payload.
     *
     * @return string
     *
     * @SuppressWarnings(PHPMD.ShortMethodName)
     */
    public function id()
    {
        return $this->decoded['uuid'] ?? $this->decoded['id'];
    }

    /**
     * Get the job tags from the payload.
     *
     * @return array<int, string>
     */
    public function tags()
    {
        return Arr::get($this->decoded, 'tags', []);
    }

    /**
     * Determine if the job is a retry of a previous job.
     *
     * @return bool
     */
    public function isRetry()
    {
        return isset($this->decoded['retry_of']);
    }

    /**
     * Get the ID of the job this job is a retry of.
     *
     * @return string|null
     */
    public function retryOf()
    {
        return $this->decoded['retry_of'] ?? null;
    }

    /**
     * Determine if the job has been silenced.
     *
     * @return bool
     */
    public function isSilenced()
    {
        return $this->decoded['silenced'] ?? false;
    }

    /**
     * Prepare the payload for storage on the queue by adding tags, etc.
     *
     * @param mixed $job
     *
     * @return $this
     */
    public function prepare($job)
    {
        // @codeCoverageIgnoreStart — Horizon process management
        return $this->set([
            'type' => $this->determineType($job),
            'tags' => $tags = $this->determineTags($job),
            'silenced' => $this->shouldBeSilenced($job, $tags),
            'pushedAt' => str_replace(',', '.', (string) microtime(true)),
        ]);
        // @codeCoverageIgnoreEnd
    }

    /**
     * Get the "type" of job being queued.
     *
     * @param mixed $job
     *
     * @return string
     */
    protected function determineType($job)
    {
        return match (true) {
            // @codeCoverageIgnoreStart — Horizon process management
            $job instanceof BroadcastEvent => 'broadcast',
            $job instanceof CallQueuedListener => 'event',
            $job instanceof SendQueuedMailable => 'mail',
            $job instanceof SendQueuedNotifications => 'notification',
            default => 'job',
            // @codeCoverageIgnoreEnd
        };
    }

    /**
     * Get the appropriate tags for the job.
     *
     * @param mixed $job
     *
     * @return array<int, string>
     */
    protected function determineTags($job)
    {
        // @codeCoverageIgnoreStart — Horizon process management
        return array_merge(
            $this->decoded['tags'] ?? [],
            ($job === null || $job === false || is_string($job)) ? [] : Tags::for($job)
        );
        // @codeCoverageIgnoreEnd
    }

    /**
     * Determine if the underlying job class should be silenced.
     *
     * @param mixed              $job
     * @param array<int, string> $tags
     *
     * @return bool
     */
    protected function shouldBeSilenced($job, array $tags = [])
    {
        // @codeCoverageIgnoreStart — Horizon process management
        if ($job === null || $job === false) {
            return false;
        }

        $underlyingJob = $this->underlyingJob($job);

        /** @var string $jobClass */
        $jobClass = is_string($underlyingJob) ? $underlyingJob : get_class($underlyingJob);

        return in_array($jobClass, config('aicl-horizon.silenced', []), true) ||
            is_a($jobClass, Silenced::class, true) ||
            count(array_intersect($tags, config('aicl-horizon.silenced_tags', []))) > 0;
        // @codeCoverageIgnoreEnd
    }

    /**
     * Get the underlying queued job.
     *
     * @param mixed $job
     *
     * @return mixed
     */
    protected function underlyingJob($job)
    {
        return match (true) {
            // @codeCoverageIgnoreStart — Horizon process management
            $job instanceof BroadcastEvent => $job->event,
            $job instanceof CallQueuedListener => $job->class,
            $job instanceof SendQueuedMailable => $job->mailable,
            $job instanceof SendQueuedNotifications => $job->notification,
            default => $job,
            // @codeCoverageIgnoreEnd
        };
    }

    /**
     * Set the given key / value pairs on the payload.
     *
     * @param array<string, mixed> $values
     *
     * @return $this
     */
    public function set(array $values)
    {
        $this->decoded = array_merge($this->decoded, $values);

        $encoded = json_encode($this->decoded);
        $this->value = ($encoded !== false && $encoded !== '') ? $encoded : '{}';

        return $this;
    }

    /**
     * Get the "command name" for the job.
     *
     * @return string
     */
    public function commandName()
    {
        return Arr::get($this->decoded, 'data.commandName');
    }

    /**
     * Get the "display name" for the job.
     *
     * @return string
     */
    public function displayName()
    {
        return Arr::get($this->decoded, 'displayName');
    }

    /**
     * Determine if the given offset exists.
     *
     * @param string $offset
     */
    public function offsetExists($offset): bool
    {
        return array_key_exists($offset, $this->decoded);
    }

    /**
     * Get the value at the current offset.
     *
     * @param string $offset
     *
     * @return mixed
     */
    #[ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        return $this->decoded[$offset];
    }

    /**
     * Set the value at the current offset.
     *
     * @param string $offset
     * @param mixed  $value
     */
    public function offsetSet($offset, $value): void
    {
        $this->decoded[$offset] = $value;
    }

    /**
     * Unset the value at the current offset.
     *
     * @param string $offset
     */
    public function offsetUnset($offset): void
    {
        unset($this->decoded[$offset]);
    }
}
