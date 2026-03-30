<?php

declare(strict_types=1);

namespace Aicl\Horizon\Jobs;

use Aicl\Horizon\Contracts\TagRepository;

/**
 * MonitorTag.
 */
class MonitorTag
{
    /**
     * Create a new job instance.
     *
     * @param string $tag The tag to monitor.
     */
    public function __construct(
        public $tag,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(TagRepository $tags)
    {
        // @codeCoverageIgnoreStart — Horizon process management
        $tags->monitor($this->tag);
        // @codeCoverageIgnoreEnd
    }
}
