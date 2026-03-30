<?php

declare(strict_types=1);

namespace Aicl\Horizon\Jobs;

use Aicl\Horizon\Contracts\JobRepository;
use Aicl\Horizon\Contracts\TagRepository;

/**
 * StopMonitoringTag.
 */
class StopMonitoringTag
{
    /**
     * Create a new job instance.
     *
     * @param string $tag The tag to stop monitoring.
     */
    public function __construct(
        public $tag,
    ) {}

    /**
     * Execute the job.
     *
     * @SuppressWarnings(PHPMD.CountInLoopExpression)
     */
    public function handle(JobRepository $jobs, TagRepository $tags)
    {
        // @codeCoverageIgnoreStart — Horizon process management
        $tags->stopMonitoring($this->tag);

        $monitored = $tags->paginate($this->tag);

        while (count($monitored) > 0) {
            $jobs->deleteMonitored($monitored);

            $offset = array_keys($monitored)[count($monitored) - 1] + 1;

            $monitored = $tags->paginate($this->tag, $offset);
        }

        $tags->forget($this->tag);
        // @codeCoverageIgnoreEnd
    }
}
