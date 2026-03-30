<?php

declare(strict_types=1);

namespace Aicl\Horizon;

use Illuminate\Broadcasting\BroadcastEvent;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Mail\SendQueuedMailable;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Collection;
use ReflectionClass;
use ReflectionProperty;
use stdClass;

/**
 * Tags.
 */
class Tags
{
    /**
     * The event that was last handled.
     *
     * @var object|null
     */
    protected static $event;

    /**
     * Determine the tags for the given job.
     *
     * @param mixed $job
     *
     * @return array<int, string>
     */
    public static function for($job)
    {
        if ($tags = static::extractExplicitTags($job)) {
            return $tags;
        }

        return static::modelsFor(static::targetsFor($job))
            ->map(fn ($model) => get_class($model).':'.$model->getKey())
            ->all();
    }

    /**
     * Extract tags from job object.
     *
     * @param mixed $job
     *
     * @return array<int, string>
     */
    public static function extractExplicitTags($job)
    {
        return $job instanceof CallQueuedListener
            // @codeCoverageIgnoreStart — Horizon process management
            ? static::tagsForListener($job)
            // @codeCoverageIgnoreEnd
            : static::explicitTags(static::targetsFor($job));
    }

    /**
     * Determine tags for the given queued listener.
     *
     * @param mixed $job
     *
     * @return array<int, string>
     */
    protected static function tagsForListener($job)
    {
        // @codeCoverageIgnoreStart — Horizon process management
        $event = static::extractEvent($job);

        static::setEvent($event);

        return collect([static::extractListener($job), $event])
            ->map(fn ($job) => static::for($job))
            ->collapse()
            ->unique()
            ->tap(function () {
                static::flushEventState();
            })
            ->toArray();
        // @codeCoverageIgnoreEnd
    }

    /**
     * Determine tags for the given job.
     *
     * @param array<int, object> $jobs
     *
     * @return array<int, string>
     */
    protected static function explicitTags(array $jobs)
    {
        return collect($jobs)
            ->map(fn ($job) => method_exists($job, 'tags') ? $job->tags(static::$event) : [])
            ->collapse()
            ->unique()
            ->all();
    }

    /**
     * Get the actual target for the given job.
     *
     * @param mixed $job
     *
     * @return array<int, object>
     */
    public static function targetsFor($job)
    {
        return match (true) {
            $job instanceof BroadcastEvent => [$job->event],
            $job instanceof CallQueuedListener => [static::extractEvent($job)],
            $job instanceof SendQueuedMailable => [$job->mailable],
            $job instanceof SendQueuedNotifications => [$job->notification],
            default => [$job],
        };
    }

    /**
     * Get the models from the given object.
     *
     * @param array<int, object> $targets
     *
     * @return Collection<int, Model>
     */
    public static function modelsFor(array $targets)
    {
        $models = [];

        foreach ($targets as $target) {
            $models[] = collect((new ReflectionClass($target))->getProperties())
                ->map(function ($property) use ($target) {
                    if (PHP_VERSION_ID < 80500) {
                        $property->setAccessible(true);
                    }

                    $value = static::getValue($property, $target);

                    if ($value instanceof Model) {
                        // @codeCoverageIgnoreStart — Horizon process management
                        return [$value];
                    } elseif ($value instanceof EloquentCollection) {
                        return $value->all();
                        // @codeCoverageIgnoreEnd
                    }
                })
                ->collapse()
                ->filter()
                ->all();
        }

        return collect($models)->collapse()->unique();
    }

    /**
     * Get the value of the given ReflectionProperty.
     *
     * @param mixed $target
     *
     * @return mixed
     */
    protected static function getValue(ReflectionProperty $property, $target)
    {
        if (method_exists($property, 'isInitialized') &&
            ! $property->isInitialized($target)) {
            return;
        }

        return $property->getValue($target);
    }

    /**
     * Extract the listener from a queued job.
     *
     * @param mixed $job
     *
     * @return mixed
     */
    protected static function extractListener($job)
    {
        // @codeCoverageIgnoreStart — Horizon process management
        return (new ReflectionClass($job->class))->newInstanceWithoutConstructor();
        // @codeCoverageIgnoreEnd
    }

    /**
     * Extract the event from a queued job.
     *
     * @param mixed $job
     *
     * @return mixed
     */
    protected static function extractEvent($job)
    {
        // @codeCoverageIgnoreStart — Horizon process management
        return isset($job->data[0]) && is_object($job->data[0])
            ? $job->data[0]
            : new stdClass;
        // @codeCoverageIgnoreEnd
    }

    /**
     * Set the event currently being handled.
     *
     * @param object $event
     */
    protected static function setEvent($event)
    {
        // @codeCoverageIgnoreStart — Horizon process management
        static::$event = $event;
        // @codeCoverageIgnoreEnd
    }

    /**
     * Flush the event currently being handled.
     */
    protected static function flushEventState()
    {
        // @codeCoverageIgnoreStart — Horizon process management
        static::$event = null;
        // @codeCoverageIgnoreEnd
    }
}
