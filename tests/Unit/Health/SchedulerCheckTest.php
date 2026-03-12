<?php

namespace Aicl\Tests\Unit\Health;

use Aicl\Health\Checks\SchedulerCheck;
use Aicl\Health\ServiceStatus;
use Aicl\Models\ScheduleHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchedulerCheckTest extends TestCase
{
    use RefreshDatabase;

    private SchedulerCheck $check;

    protected function setUp(): void
    {
        parent::setUp();
        $this->check = new SchedulerCheck;
    }

    public function test_returns_down_when_no_history(): void
    {
        $result = $this->check->check();

        $this->assertSame(ServiceStatus::Down, $result->status);
        $this->assertSame('Scheduler', $result->name);
    }

    public function test_returns_healthy_when_recent_run(): void
    {
        ScheduleHistory::factory()->create([
            'status' => 'success',
            'started_at' => now()->subMinute(),
        ]);

        $result = $this->check->check();

        $this->assertSame(ServiceStatus::Healthy, $result->status);
    }

    public function test_returns_degraded_when_stale(): void
    {
        config(['aicl.scheduler.health_degraded_minutes' => 5]);
        config(['aicl.scheduler.health_down_minutes' => 15]);

        ScheduleHistory::factory()->create([
            'status' => 'success',
            'started_at' => now()->subMinutes(7),
        ]);

        $result = $this->check->check();

        $this->assertSame(ServiceStatus::Degraded, $result->status);
    }

    public function test_returns_down_when_very_stale(): void
    {
        config(['aicl.scheduler.health_down_minutes' => 15]);

        ScheduleHistory::factory()->create([
            'status' => 'success',
            'started_at' => now()->subMinutes(20),
        ]);

        $result = $this->check->check();

        $this->assertSame(ServiceStatus::Down, $result->status);
    }

    public function test_includes_failed_count_in_details(): void
    {
        ScheduleHistory::factory()->create([
            'status' => 'success',
            'started_at' => now()->subMinute(),
        ]);
        ScheduleHistory::factory()->count(2)->create([
            'status' => 'failed',
            'started_at' => now()->subHours(2),
        ]);

        $result = $this->check->check();

        $this->assertArrayHasKey('Failed (24h)', $result->details);
        $this->assertSame('2', $result->details['Failed (24h)']);
    }

    public function test_order_is_55(): void
    {
        $this->assertSame(55, $this->check->order());
    }
}
