<?php

namespace Aicl\Tests\Unit\Models;

use Aicl\Models\ScheduleHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ScheduleHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_schedule_history(): void
    {
        $history = ScheduleHistory::factory()->create();

        $this->assertDatabaseHas('schedule_history', [
            'id' => $history->id,
        ]);
    }

    public function test_successful_scope(): void
    {
        ScheduleHistory::factory()->count(2)->create(['status' => 'success']);
        ScheduleHistory::factory()->create(['status' => 'failed']);

        $this->assertCount(2, ScheduleHistory::query()->successful()->get());
    }

    public function test_failed_scope(): void
    {
        ScheduleHistory::factory()->create(['status' => 'success']);
        ScheduleHistory::factory()->count(3)->create(['status' => 'failed']);

        $this->assertCount(3, ScheduleHistory::query()->failed()->get());
    }

    public function test_for_command_scope(): void
    {
        ScheduleHistory::factory()->create(['command' => 'backup:run']);
        ScheduleHistory::factory()->create(['command' => 'backup:clean']);
        ScheduleHistory::factory()->create(['command' => 'backup:run']);

        $this->assertCount(2, ScheduleHistory::query()->forCommand('backup:run')->get());
    }

    public function test_recent_scope(): void
    {
        ScheduleHistory::factory()->create(['started_at' => now()->subHours(2)]);
        ScheduleHistory::factory()->create(['started_at' => now()->subHours(25)]);

        $this->assertCount(1, ScheduleHistory::query()->recent(24)->get());
    }

    public function test_casts_started_at_to_datetime(): void
    {
        $history = ScheduleHistory::factory()->create();

        $this->assertInstanceOf(Carbon::class, $history->started_at);
    }

    public function test_casts_finished_at_to_datetime(): void
    {
        $history = ScheduleHistory::factory()->create([
            'finished_at' => now(),
        ]);

        $this->assertInstanceOf(Carbon::class, $history->finished_at);
    }

    public function test_factory_running_state(): void
    {
        $history = ScheduleHistory::factory()->running()->create();

        $this->assertSame('running', $history->status);
        $this->assertNull($history->finished_at);
    }

    public function test_factory_failed_state(): void
    {
        $history = ScheduleHistory::factory()->failed()->create();

        $this->assertSame('failed', $history->status);
        $this->assertSame(1, $history->exit_code);
    }

    public function test_factory_skipped_state(): void
    {
        $history = ScheduleHistory::factory()->skipped()->create();

        $this->assertSame('skipped', $history->status);
    }

    public function test_timestamps_disabled(): void
    {
        $history = new ScheduleHistory;

        $this->assertFalse($history->timestamps);
    }
}
