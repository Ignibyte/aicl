<?php

declare(strict_types=1);

namespace Aicl\Horizon\Listeners;

use Aicl\Horizon\Contracts\TagRepository;
use Aicl\Horizon\Events\JobPushed;

/**
 * StoreMonitoredTags.
 */
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
     */
    public function __construct(TagRepository $tags)
    {
        $this->tags = $tags;
    }

    /**
     * Handle the event.
     */
    public function handle(JobPushed $event)
    {
        $monitoring = $this->tags->monitored($event->payload->tags());

        if ($monitoring !== []) {
            $this->tags->add($event->payload->id(), $monitoring);
        }
    }
}
