<?php

namespace Aicl\Horizon\Listeners;

use Aicl\Horizon\Contracts\TagRepository;
use Aicl\Horizon\Events\JobPushed;

class StoreMonitoredTags
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
    public function handle(JobPushed $event)
    {
        $monitoring = $this->tags->monitored($event->payload->tags());

        if (! empty($monitoring)) {
            $this->tags->add($event->payload->id(), $monitoring);
        }
    }
}
