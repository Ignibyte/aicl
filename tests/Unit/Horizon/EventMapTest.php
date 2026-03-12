<?php

namespace Aicl\Tests\Unit\Horizon;

use Aicl\Horizon\EventMap;
use Aicl\Horizon\Events;
use Aicl\Horizon\Listeners;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed as LaravelJobFailed;
use PHPUnit\Framework\TestCase;

class EventMapTest extends TestCase
{
    private object $eventMap;

    protected function setUp(): void
    {
        parent::setUp();
        $this->eventMap = new class
        {
            use EventMap;

            public function getEvents(): array
            {
                return $this->events;
            }
        };
    }

    public function test_event_map_contains_all_horizon_events(): void
    {
        $events = $this->eventMap->getEvents();

        $expectedEvents = [
            Events\JobPending::class,
            Events\JobPushed::class,
            Events\JobReserved::class,
            Events\JobReleased::class,
            Events\JobDeleted::class,
            Events\JobsMigrated::class,
            Events\JobFailed::class,
            Events\MasterSupervisorLooped::class,
            Events\SupervisorLooped::class,
            Events\WorkerProcessRestarting::class,
            Events\SupervisorProcessRestarting::class,
            Events\LongWaitDetected::class,
        ];

        foreach ($expectedEvents as $event) {
            $this->assertArrayHasKey($event, $events, "Missing event: {$event}");
        }
    }

    public function test_event_map_includes_laravel_queue_events(): void
    {
        $events = $this->eventMap->getEvents();

        $this->assertArrayHasKey(JobExceptionOccurred::class, $events);
        $this->assertArrayHasKey(LaravelJobFailed::class, $events);
    }

    public function test_job_pending_has_store_job_listener(): void
    {
        $events = $this->eventMap->getEvents();

        $this->assertContains(Listeners\StoreJob::class, $events[Events\JobPending::class]);
    }

    public function test_job_reserved_has_expected_listeners(): void
    {
        $events = $this->eventMap->getEvents();

        $this->assertContains(Listeners\MarkJobAsReserved::class, $events[Events\JobReserved::class]);
        $this->assertContains(Listeners\StartTimingJob::class, $events[Events\JobReserved::class]);
    }

    public function test_job_deleted_has_completion_listeners(): void
    {
        $events = $this->eventMap->getEvents();

        $this->assertContains(Listeners\MarkJobAsComplete::class, $events[Events\JobDeleted::class]);
        $this->assertContains(Listeners\UpdateJobMetrics::class, $events[Events\JobDeleted::class]);
    }

    public function test_job_failed_has_failure_listeners(): void
    {
        $events = $this->eventMap->getEvents();

        $this->assertContains(Listeners\MarkJobAsFailed::class, $events[Events\JobFailed::class]);
        $this->assertContains(Listeners\StoreTagsForFailedJob::class, $events[Events\JobFailed::class]);
    }

    public function test_master_supervisor_looped_has_maintenance_listeners(): void
    {
        $events = $this->eventMap->getEvents();

        $listeners = $events[Events\MasterSupervisorLooped::class];

        $this->assertContains(Listeners\TrimRecentJobs::class, $listeners);
        $this->assertContains(Listeners\TrimFailedJobs::class, $listeners);
        $this->assertContains(Listeners\TrimMonitoredJobs::class, $listeners);
        $this->assertContains(Listeners\ExpireSupervisors::class, $listeners);
        $this->assertContains(Listeners\MonitorMasterSupervisorMemory::class, $listeners);
    }

    public function test_supervisor_looped_has_process_listeners(): void
    {
        $events = $this->eventMap->getEvents();

        $listeners = $events[Events\SupervisorLooped::class];

        $this->assertContains(Listeners\PruneTerminatingProcesses::class, $listeners);
        $this->assertContains(Listeners\MonitorSupervisorMemory::class, $listeners);
        $this->assertContains(Listeners\MonitorWaitTimes::class, $listeners);
    }

    public function test_long_wait_detected_fires_send_notification(): void
    {
        $events = $this->eventMap->getEvents();

        $this->assertContains(Listeners\SendNotification::class, $events[Events\LongWaitDetected::class]);
    }

    public function test_laravel_job_failed_has_marshal_listener(): void
    {
        $events = $this->eventMap->getEvents();

        $this->assertContains(Listeners\ForgetJobTimer::class, $events[LaravelJobFailed::class]);
        $this->assertContains(Listeners\MarshalFailedEvent::class, $events[LaravelJobFailed::class]);
    }

    public function test_total_listener_count(): void
    {
        $events = $this->eventMap->getEvents();

        $totalListeners = 0;
        foreach ($events as $listeners) {
            $totalListeners += count($listeners);
        }

        // 22 listener slots mapped across 14 events
        $this->assertSame(22, $totalListeners);
    }
}
