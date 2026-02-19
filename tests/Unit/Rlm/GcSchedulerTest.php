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
        $this->assertStringContainsString('02:00', $staleEvent->expression);
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
        $this->assertStringContainsString('02:30', $cleanupEvent->expression);
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
        $this->assertStringContainsString('6:00', $statsEvent->expression);
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

        // Create an old GC log (>90 days)
        $oldFile = $logsPath.'/rlm-gc-2024-01.log';
        file_put_contents($oldFile, 'old gc log');
        touch($oldFile, Carbon::now()->subDays(100)->timestamp);

        // Create a recent GC log (<90 days)
        $recentFile = $logsPath.'/rlm-gc-2026-06.log';
        file_put_contents($recentFile, 'recent gc log');
        touch($recentFile, Carbon::now()->subDays(10)->timestamp);

        // Call pruneRlmLogs via reflection
        $provider = $this->app->getProvider(AiclServiceProvider::class);
        $method = new \ReflectionMethod($provider, 'pruneRlmLogs');
        $method->setAccessible(true);
        $method->invoke($provider);

        $this->assertFileDoesNotExist($oldFile);
        $this->assertFileExists($recentFile);

        // Cleanup
        @unlink($recentFile);
    }

    public function test_prune_rlm_logs_removes_old_health_logs(): void
    {
        $logsPath = storage_path('logs');

        // Create an old health log (>365 days)
        $oldFile = $logsPath.'/rlm-health-2024-01.log';
        file_put_contents($oldFile, 'old health log');
        touch($oldFile, Carbon::now()->subDays(400)->timestamp);

        // Create a recent health log (<365 days)
        $recentFile = $logsPath.'/rlm-health-2026-01.log';
        file_put_contents($recentFile, 'recent health log');
        touch($recentFile, Carbon::now()->subDays(30)->timestamp);

        // Call pruneRlmLogs via reflection
        $provider = $this->app->getProvider(AiclServiceProvider::class);
        $method = new \ReflectionMethod($provider, 'pruneRlmLogs');
        $method->setAccessible(true);
        $method->invoke($provider);

        $this->assertFileDoesNotExist($oldFile);
        $this->assertFileExists($recentFile);

        // Cleanup
        @unlink($recentFile);
    }

    public function test_prune_rlm_logs_keeps_recent_files(): void
    {
        $logsPath = storage_path('logs');

        // Recent GC log (30 days old)
        $gcFile = $logsPath.'/rlm-gc-2026-07.log';
        file_put_contents($gcFile, 'recent gc');
        touch($gcFile, Carbon::now()->subDays(30)->timestamp);

        // Recent health log (100 days old)
        $healthFile = $logsPath.'/rlm-health-2026-05.log';
        file_put_contents($healthFile, 'recent health');
        touch($healthFile, Carbon::now()->subDays(100)->timestamp);

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
        $event = new RlmMaintenanceComplete();

        $this->assertSame(0, $event->stalePatternCount);
        $this->assertSame(0, $event->cleanedRecordCount);
        $this->assertSame(0, $event->brokenLinkCount);
    }
}
