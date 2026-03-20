<?php

declare(strict_types=1);

namespace Aicl\Listeners;

use Aicl\Models\ScheduleHistory;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Log;

/** Event subscriber that records scheduled task execution history to the schedule_histories table. */
class ScheduleEventSubscriber
{
    public function handleStarting(ScheduledTaskStarting $event): void
    {
        $task = $event->task;

        $history = ScheduleHistory::query()->create([
            'command' => $this->getCommandName($task),
            'description' => $task->description ?? null,
            'expression' => $task->expression,
            'status' => 'running',
            'started_at' => now(),
        ]);

        $task->_scheduleHistoryId = $history->id;
    }

    public function handleFinished(ScheduledTaskFinished $event): void
    {
        $historyId = $event->task->_scheduleHistoryId ?? null;

        if (! $historyId) {
            Log::warning('ScheduleEventSubscriber: No _scheduleHistoryId on finished task', [
                'command' => $this->getCommandName($event->task),
            ]);

            return;
        }

        /** @var ScheduleHistory|null $history */
        $history = ScheduleHistory::query()->find($historyId);

        if (! $history) {
            return;
        }

        $output = $this->captureOutput($event->task);

        $history->update([
            'status' => 'success',
            'exit_code' => $event->task->exitCode ?? 0,
            'output' => $output,
            'duration_ms' => (int) $history->started_at->diffInMilliseconds(now()),
            'finished_at' => now(),
        ]);
    }

    public function handleFailed(ScheduledTaskFailed $event): void
    {
        $historyId = $event->task->_scheduleHistoryId ?? null;

        if (! $historyId) {
            Log::warning('ScheduleEventSubscriber: No _scheduleHistoryId on failed task', [
                'command' => $this->getCommandName($event->task),
            ]);

            return;
        }

        /** @var ScheduleHistory|null $history */
        $history = ScheduleHistory::query()->find($historyId);

        if (! $history) {
            return;
        }

        $output = $this->captureOutput($event->task);

        if ($event->exception) {
            $exceptionOutput = $event->exception->getMessage();
            $output = $output ? "{$output}\n\n{$exceptionOutput}" : $exceptionOutput;
        }

        $history->update([
            'status' => 'failed',
            'exit_code' => $event->task->exitCode ?? 1,
            'output' => $output,
            'duration_ms' => (int) $history->started_at->diffInMilliseconds(now()),
            'finished_at' => now(),
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            ScheduledTaskStarting::class => 'handleStarting',
            ScheduledTaskFinished::class => 'handleFinished',
            ScheduledTaskFailed::class => 'handleFailed',
        ];
    }

    protected function getCommandName(Event $task): string
    {
        if ($task->command) {
            // Strip quotes first, then the PHP binary and artisan prefix for readability
            $command = str_replace("'", '', $task->command);
            $command = preg_replace('/^.*?artisan\s+/', '', $command) ?? $command;

            return trim($command);
        }

        return $task->description ?: 'Closure';
    }

    protected function captureOutput(Event $task): ?string
    {
        $outputPath = $task->output ?? null;

        if (! $outputPath || $outputPath === '/dev/null' || ! file_exists($outputPath)) {
            return null;
        }

        $maxBytes = max(0, (int) config('aicl.scheduler.output_max_bytes', 10240));
        $fileSize = filesize($outputPath);

        if ($fileSize === 0 || $fileSize === false) {
            return null;
        }

        $content = file_get_contents($outputPath, false, null, 0, $maxBytes);

        if ($content === false || trim($content) === '') {
            return null;
        }

        if ($fileSize > $maxBytes) {
            $content .= "\n\n[TRUNCATED — {$fileSize} bytes total]";
        }

        return $content;
    }
}
