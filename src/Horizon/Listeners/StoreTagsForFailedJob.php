<?php

namespace Aicl\Horizon\Listeners;

use Aicl\Horizon\Contracts\TagRepository;
use Aicl\Horizon\Events\JobFailed;

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
     *
     * @return void
     */
    public function __construct(TagRepository $tags)
    {
        $this->tags = $tags;
    }

    /**
     * Handle the event.
     *
     * @return void
     */
    public function handle(JobFailed $event)
    {
        $tags = collect($event->payload->tags())
            ->map(fn ($tag) => 'failed:'.$tag)
            ->all();

        $this->tags->addTemporary(
            config('aicl-horizon.trim.failed', 2880), $event->payload->id(), $tags
        );
    }
}
