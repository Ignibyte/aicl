<?php

declare(strict_types=1);

namespace Aicl\Horizon\Listeners;

use Aicl\Horizon\Contracts\TagRepository;
use Aicl\Horizon\Events\JobFailed;

/**
 * StoreTagsForFailedJob.
 */
class StoreTagsForFailedJob
{
    /**
     * The tag repository implementation.
     *
     * @var TagRepository
     */
    public $tags;

    /**
     * Create a new listener instance.
     */
    public function __construct(TagRepository $tags)
    {
        $this->tags = $tags;
    }

    /**
     * Handle the event.
     */
    public function handle(JobFailed $event)
    {
        // @codeCoverageIgnoreStart — Horizon process management
        $tags = collect($event->payload->tags())
            ->map(fn ($tag) => 'failed:'.$tag)
            ->all();

        $this->tags->addTemporary(
            config('aicl-horizon.trim.failed', 2880), $event->payload->id(), $tags
        );
        // @codeCoverageIgnoreEnd
    }
}
