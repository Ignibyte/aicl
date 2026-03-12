<?php

namespace Aicl\Horizon\Jobs;

use Aicl\Horizon\Contracts\JobRepository;
use Aicl\Horizon\Contracts\TagRepository;

class StopMonitoringTag
{
    /**
     * Create a new job instance.
     *
     * @param  string  $tag  The tag to stop monitoring.
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
    public function handle(JobRepository $jobs, TagRepository $tags)
    {
        $tags->stopMonitoring($this->tag);

        $monitored = $tags->paginate($this->tag);

        while (count($monitored) > 0) {
            $jobs->deleteMonitored($monitored);

            $offset = array_keys($monitored)[count($monitored) - 1] + 1;

            $monitored = $tags->paginate($this->tag, $offset);
        }

        $tags->forget($this->tag);
    }
}
