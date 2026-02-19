<?php

namespace Aicl\Tests\Unit\Rlm;

use Aicl\AiclServiceProvider;
use Aicl\Events\RlmMaintenanceComplete;
use Carbon\Carbon;
use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

class GcSchedulerTest extends TestCase
{
    // ─── F.1: Schedule registration ───────────────────────────────

    public function test_rlm_schedule_is_registered(): void
    {
        $schedule = $this->app->make(Schedule::class);
        $events = $schedule->events();

        $names = collect($events)->map(fn ($e) => $e->command ?? $e->description)->toArray();

        // Verify at least the named callback events are registered
        $this->assertContains('rlm-proof-link-integrity', $names);
        $this->assertContains('rlm-log-rotation', $names);
    }

    public function test_discover_patterns_stale_is_scheduled_weekly(): void
    {
        $schedule = $this->app->make(Schedule::class);
        $events = $schedule->events();

        $staleEvent = collect($events)->first(function ($event) {
            return isset($event->command)
                && str_contains($event->command, 'discover-patterns')
                && str_contains($event->command, '--stale');
        });

        $this->assertNotNull($staleEvent, 'discover-patterns --stale should be scheduled');
        // Cron: minute=0, hour=2, day=*, month=*, dow=0 (Sunday)
        $this->assertSame('0 2 * * 0', $staleEvent->expression);
    }

    public function test_rlm_cleanup_is_scheduled_weekly(): void
    {
        $schedule = $this->app->make(Schedule::class);
        $events = $schedule->events();

        $cleanupEvent = collect($events)->first(function ($event) {
            return isset($event->command)
                && str_contains($event->command, 'aicl:rlm')
                && str_contains($event->command, 'cleanup');
        });

        $this->assertNotNull($cleanupEvent, 'aicl:rlm cleanup should be scheduled');
        // Cron: minute=30, hour=2, day=*, month=*, dow=0 (Sunday)
        $this->assertSame('30 2 * * 0', $cleanupEvent->expression);
    }

    public function test_rlm_stats_is_scheduled_daily(): void
    {
        $schedule = $this->app->make(Schedule::class);
        $events = $schedule->events();

        $statsEvent = collect($events)->first(function ($event) {
            return isset($event->command)
                && str_contains($event->command, 'aicl:rlm')
                && str_contains($event->command, 'stats');
        });

        $this->assertNotNull($statsEvent, 'aicl:rlm stats should be scheduled');
        // Cron: minute=0, hour=6, daily
        $this->assertSame('0 6 * * *', $statsEvent->expression);
    }

    public function test_scheduled_events_use_one_server(): void
    {
        $schedule = $this->app->make(Schedule::class);
        $events = $schedule->events();

        // Check named callback events
        $proofLink = collect($events)->first(fn ($e) => ($e->description ?? '') === 'rlm-proof-link-integrity');
        $logRotation = collect($events)->first(fn ($e) => ($e->description ?? '') === 'rlm-log-rotation');

        $this->assertNotNull($proofLink);
        $this->assertNotNull($logRotation);
        $this->assertTrue($proofLink->onOneServer, 'proof-link-integrity should use onOneServer');
        $this->assertTrue($logRotation->onOneServer, 'log-rotation should use onOneServer');
    }

    // ─── F.2: Log rotation ────────────────────────────────────────

    public function test_prune_rlm_logs_removes_old_gc_logs(): void
    {
        $logsPath = storage_path('logs');
        $futureTime = Carbon::now()->addDays(100);

        // Create "old" file with current real timestamp
        $oldFile = $logsPath.'/rlm-gc-test-old.log';
        file_put_contents($oldFile, 'old gc log');

        // Create "recent" file with future timestamp (within 90 days of shifted now)
        $recentFile = $logsPath.'/rlm-gc-test-recent.log';
        file_put_contents($recentFile, 'recent gc log');
        touch($recentFile, $futureTime->timestamp);

        // Shift now forward 100 days — old file is 100 days old, recent is 0 days old
        Carbon::setTestNow($futureTime);

        $provider = $this->app->getProvider(AiclServiceProvider::class);
        $method = new \ReflectionMethod($provider, 'pruneRlmLogs');
        $method->setAccessible(true);
        $method->invoke($provider);

        $this->assertFileDoesNotExist($oldFile);
        $this->assertFileExists($recentFile);

        // Cleanup
        @unlink($recentFile);
        Carbon::setTestNow();
    }

    public function test_prune_rlm_logs_removes_old_health_logs(): void
    {
        $logsPath = storage_path('logs');
        $futureTime = Carbon::now()->addDays(400);

        // Create "old" file with current real timestamp
        $oldFile = $logsPath.'/rlm-health-test-old.log';
        file_put_contents($oldFile, 'old health log');

        // Create "recent" file with future timestamp
        $recentFile = $logsPath.'/rlm-health-test-recent.log';
        file_put_contents($recentFile, 'recent health log');
        touch($recentFile, $futureTime->timestamp);

        // Shift now forward 400 days — old file is 400 days old, recent is 0 days old
        Carbon::setTestNow($futureTime);

        $provider = $this->app->getProvider(AiclServiceProvider::class);
        $method = new \ReflectionMethod($provider, 'pruneRlmLogs');
        $method->setAccessible(true);
        $method->invoke($provider);

        $this->assertFileDoesNotExist($oldFile);
        $this->assertFileExists($recentFile);

        // Cleanup
        @unlink($recentFile);
        Carbon::setTestNow();
    }

    public function test_prune_rlm_logs_keeps_recent_files(): void
    {
        $logsPath = storage_path('logs');

        // Create files — they will be recent (created "now")
        $gcFile = $logsPath.'/rlm-gc-test-keep.log';
        $healthFile = $logsPath.'/rlm-health-test-keep.log';
        file_put_contents($gcFile, 'recent gc');
        file_put_contents($healthFile, 'recent health');

        $provider = $this->app->getProvider(AiclServiceProvider::class);
        $method = new \ReflectionMethod($provider, 'pruneRlmLogs');
        $method->setAccessible(true);
        $method->invoke($provider);

        $this->assertFileExists($gcFile);
        $this->assertFileExists($healthFile);

        // Cleanup
        @unlink($gcFile);
        @unlink($healthFile);
    }

    // ─── F.5: RlmMaintenanceComplete event ────────────────────────

    public function test_rlm_maintenance_complete_event_has_properties(): void
    {
        $event = new RlmMaintenanceComplete(
            stalePatternCount: 3,
            cleanedRecordCount: 10,
            brokenLinkCount: 2,
        );

        $this->assertSame(3, $event->stalePatternCount);
        $this->assertSame(10, $event->cleanedRecordCount);
        $this->assertSame(2, $event->brokenLinkCount);
    }

    public function test_rlm_maintenance_complete_event_defaults_to_zero(): void
    {
        $event = new RlmMaintenanceComplete;

        $this->assertSame(0, $event->stalePatternCount);
        $this->assertSame(0, $event->cleanedRecordCount);
        $this->assertSame(0, $event->brokenLinkCount);
    }
}
