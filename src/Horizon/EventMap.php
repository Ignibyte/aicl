<?php

declare(strict_types=1);

namespace Aicl\Horizon;

use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;

/**
 * EventMap.
 */
trait EventMap
{
    /**
     * All of the Horizon event / listener mappings.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $events = [
        Events\JobPending::class => [
            Listeners\StoreJob::class,
        ],

        Events\JobPushed::class => [
            Listeners\StoreMonitoredTags::class,
        ],

        Events\JobReserved::class => [
            Listeners\MarkJobAsReserved::class,
            Listeners\StartTimingJob::class,
        ],

        Events\JobReleased::class => [
            Listeners\MarkJobAsReleased::class,
        ],

        Events\JobDeleted::class => [
            Listeners\MarkJobAsComplete::class,
            Listeners\UpdateJobMetrics::class,
        ],

        Events\JobsMigrated::class => [
            Listeners\MarkJobsAsMigrated::class,
        ],

        JobExceptionOccurred::class => [
            Listeners\ForgetJobTimer::class,
        ],

        JobFailed::class => [
            Listeners\ForgetJobTimer::class,
            Listeners\MarshalFailedEvent::class,
        ],

        Events\JobFailed::class => [
            Listeners\MarkJobAsFailed::class,
            Listeners\StoreTagsForFailedJob::class,
        ],

        Events\MasterSupervisorLooped::class => [
            Listeners\TrimRecentJobs::class,
            Listeners\TrimFailedJobs::class,
            Listeners\TrimMonitoredJobs::class,
            Listeners\ExpireSupervisors::class,
            Listeners\MonitorMasterSupervisorMemory::class,
        ],

        Events\SupervisorLooped::class => [
            Listeners\PruneTerminatingProcesses::class,
            Listeners\MonitorSupervisorMemory::class,
            Listeners\MonitorWaitTimes::class,
        ],

        Events\WorkerProcessRestarting::class => [
            //
        ],

        Events\SupervisorProcessRestarting::class => [
            //
        ],

        Events\LongWaitDetected::class => [
            Listeners\SendNotification::class,
        ],
    ];
}
