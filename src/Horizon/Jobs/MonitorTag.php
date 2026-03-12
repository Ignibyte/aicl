<?php

namespace Aicl\Horizon\Jobs;

use Aicl\Horizon\Contracts\TagRepository;

class MonitorTag
{
    /**
     * Create a new job instance.
     *
     * @param  string  $tag  The tag to monitor.
     * @return void
     */
    public function __construct(
        public $tag,
    ) {}

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(TagRepository $tags)
    {
        $tags->monitor($this->tag);
    }
}
