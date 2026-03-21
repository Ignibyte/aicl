<?php

namespace Aicl\Tests\Unit\Listeners;

use Aicl\Listeners\ScheduleEventSubscriber;
use Aicl\Models\ScheduleHistory;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\EventMutex;
use Illuminate\Events\Dispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ScheduleEventSubscriberTest extends TestCase
{
    use RefreshDatabase;

    private ScheduleEventSubscriber $subscriber;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subscriber = new ScheduleEventSubscriber;
    }

    public function test_subscribe_returns_event_mapping(): void
    {
        $dispatcher = $this->app->make(Dispatcher::class);
        $result = $this->subscriber->subscribe($dispatcher);

        $this->assertArrayHasKey(ScheduledTaskStarting::class, $result);
        $this->assertArrayHasKey(ScheduledTaskFinished::class, $result);
        $this->assertArrayHasKey(ScheduledTaskFailed::class, $result);
    }

    public function test_handle_starting_creates_history_record(): void
    {
        $task = $this->createMockTask('backup:run');
        $event = new ScheduledTaskStarting($task);

        $this->subscriber->handleStarting($event);

        $this->assertDatabaseHas('schedule_history', [
            'command' => 'backup:run',
            'status' => 'running',
        ]);
    }

    public function test_handle_starting_sets_history_id_on_task(): void
    {
        $task = $this->createMockTask('backup:run');
        $event = new ScheduledTaskStarting($task);

        $this->subscriber->handleStarting($event);

        /** @phpstan-ignore-next-line */
        $this->assertNotNull($task->_scheduleHistoryId);
    }

    public function test_handle_finished_updates_history(): void
    {
        $task = $this->createMockTask('backup:run');
        $startEvent = new ScheduledTaskStarting($task);
        $this->subscriber->handleStarting($startEvent);

        $task->exitCode = 0;
        $finishEvent = new ScheduledTaskFinished($task, 0);
        $this->subscriber->handleFinished($finishEvent);

        $history = ScheduleHistory::query()->first();
        /** @phpstan-ignore-next-line */
        $this->assertSame('success', $history->status);
        /** @phpstan-ignore-next-line */
        $this->assertSame(0, $history->exit_code);
        /** @phpstan-ignore-next-line */
        $this->assertNotNull($history->finished_at);
    }

    public function test_handle_failed_updates_history(): void
    {
        $task = $this->createMockTask('backup:run');
        $startEvent = new ScheduledTaskStarting($task);
        $this->subscriber->handleStarting($startEvent);

        $exception = new \RuntimeException('Backup disk full');
        $task->exitCode = 1;
        $failEvent = new ScheduledTaskFailed($task, $exception);
        $this->subscriber->handleFailed($failEvent);

        $history = ScheduleHistory::query()->first();
        /** @phpstan-ignore-next-line */
        $this->assertSame('failed', $history->status);
        /** @phpstan-ignore-next-line */
        $this->assertSame(1, $history->exit_code);
        /** @phpstan-ignore-next-line */
        $this->assertStringContainsString('Backup disk full', $history->output);
    }

    public function test_handle_finished_without_history_id_does_nothing(): void
    {
        $task = $this->createMockTask('backup:run');
        $task->exitCode = 0;
        $finishEvent = new ScheduledTaskFinished($task, 0);

        $this->subscriber->handleFinished($finishEvent);

        $this->assertDatabaseCount('schedule_history', 0);
    }

    public function test_strips_artisan_prefix_from_command(): void
    {
        $task = $this->createMockTask('/usr/bin/php artisan backup:run');
        $event = new ScheduledTaskStarting($task);

        $this->subscriber->handleStarting($event);

        $this->assertDatabaseHas('schedule_history', [
            'command' => 'backup:run',
        ]);
    }

    public function test_uses_description_for_closure_tasks(): void
    {
        $task = $this->createMockTask(null, 'Daily cleanup');
        $event = new ScheduledTaskStarting($task);

        $this->subscriber->handleStarting($event);

        $this->assertDatabaseHas('schedule_history', [
            'command' => 'Daily cleanup',
        ]);
    }

    private function createMockTask(?string $command = null, ?string $description = null): Event
    {
        $mutex = Mockery::mock(EventMutex::class);
        /** @phpstan-ignore-next-line */
        $task = new Event($mutex, $command ?? 'ls');

        if ($command) {
            $task->command = $command;
        } else {
            $task->command = null;
        }

        $task->description = $description;
        $task->expression = '* * * * *';
        $task->output = '/dev/null';

        return $task;
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
