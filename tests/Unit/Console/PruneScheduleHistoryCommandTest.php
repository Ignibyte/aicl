<?php

namespace Aicl\Tests\Unit\Console;

use Aicl\Models\ScheduleHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PruneScheduleHistoryCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_prunes_old_records(): void
    {
        ScheduleHistory::factory()->create([
            'started_at' => now()->subDays(40),
        ]);
        ScheduleHistory::factory()->create([
            'started_at' => now()->subDays(10),
        ]);

        $this->artisan('schedule:prune-history', ['--days' => 30])
            ->assertSuccessful()
            ->expectsOutputToContain('Pruned 1');

        $this->assertDatabaseCount('schedule_history', 1);
    }

    public function test_uses_config_default_when_no_option(): void
    {
        config(['aicl.scheduler.history_retention_days' => 7]);

        ScheduleHistory::factory()->create([
            'started_at' => now()->subDays(10),
        ]);
        ScheduleHistory::factory()->create([
            'started_at' => now()->subDays(3),
        ]);

        $this->artisan('schedule:prune-history')
            ->assertSuccessful()
            ->expectsOutputToContain('Pruned 1');

        $this->assertDatabaseCount('schedule_history', 1);
    }

    public function test_prunes_nothing_when_all_recent(): void
    {
        ScheduleHistory::factory()->count(3)->create([
            'started_at' => now()->subDay(),
        ]);

        $this->artisan('schedule:prune-history', ['--days' => 30])
            ->assertSuccessful()
            ->expectsOutputToContain('Pruned 0');

        $this->assertDatabaseCount('schedule_history', 3);
    }
}
