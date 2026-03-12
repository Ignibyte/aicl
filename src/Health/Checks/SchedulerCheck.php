<?php

namespace Aicl\Health\Checks;

use Aicl\Health\Contracts\ServiceHealthCheck;
use Aicl\Health\ServiceCheckResult;
use Aicl\Models\ScheduleHistory;
use Throwable;

class SchedulerCheck implements ServiceHealthCheck
{
    public function check(): ServiceCheckResult
    {
        try {
            $lastRun = ScheduleHistory::query()->latest('started_at')->first();

            if (! $lastRun) {
                return ServiceCheckResult::down(
                    name: 'Scheduler',
                    icon: 'heroicon-o-calendar',
                    error: 'No scheduled tasks have been recorded. The scheduler may not be running.',
                );
            }

            $minutesSince = $lastRun->started_at->diffInMinutes(now(), absolute: true);
            $downMinutes = (int) config('aicl.scheduler.health_down_minutes', 15);
            $degradedMinutes = (int) config('aicl.scheduler.health_degraded_minutes', 5);

            $details = [
                'Last Task' => $lastRun->command,
                'Last Run' => $lastRun->started_at->diffForHumans(),
                'Status' => $lastRun->status,
            ];

            $recentFailed = ScheduleHistory::query()
                ->failed()
                ->recent(24)
                ->count();

            if ($recentFailed > 0) {
                $details['Failed (24h)'] = (string) $recentFailed;
            }

            if ($minutesSince >= $downMinutes) {
                return ServiceCheckResult::down(
                    name: 'Scheduler',
                    icon: 'heroicon-o-calendar',
                    details: $details,
                    error: "No task has run in {$minutesSince} minutes (threshold: {$downMinutes}m).",
                );
            }

            if ($minutesSince >= $degradedMinutes) {
                return ServiceCheckResult::degraded(
                    name: 'Scheduler',
                    icon: 'heroicon-o-calendar',
                    details: $details,
                    error: "No task has run in {$minutesSince} minutes (threshold: {$degradedMinutes}m).",
                );
            }

            return ServiceCheckResult::healthy(
                name: 'Scheduler',
                icon: 'heroicon-o-calendar',
                details: $details,
            );
        } catch (Throwable $e) {
            return ServiceCheckResult::down(
                name: 'Scheduler',
                icon: 'heroicon-o-calendar',
                error: $e->getMessage(),
            );
        }
    }

    public function order(): int
    {
        return 55;
    }
}
