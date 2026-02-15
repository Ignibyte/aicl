<?php

namespace Aicl\Tests\Unit\Jobs;

use Aicl\Jobs\CleanStaleDeliveriesJob;
use Aicl\Models\NotificationLog;
use Aicl\Notifications\Enums\ChannelType;
use Aicl\Notifications\Enums\DeliveryStatus;
use Aicl\Notifications\Models\NotificationChannel;
use Aicl\Notifications\Models\NotificationDeliveryLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class CleanStaleDeliveriesJobTest extends TestCase
{
    use RefreshDatabase;

    private NotificationLog $notificationLog;

    private NotificationChannel $channel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->notificationLog = NotificationLog::create([
            'type' => 'test_notification',
            'notifiable_type' => 'App\Models\User',
            'notifiable_id' => 1,
            'channels' => ['database'],
            'channel_status' => ['database' => 'pending'],
            'data' => ['message' => 'test'],
        ]);

        $this->channel = NotificationChannel::create([
            'name' => 'Test Email',
            'slug' => 'test-email',
            'type' => ChannelType::Email,
            'config' => [],
            'is_active' => true,
        ]);
    }

    private function createDeliveryLog(DeliveryStatus $status, int $hoursAgo, array $extra = []): NotificationDeliveryLog
    {
        $log = NotificationDeliveryLog::create(array_merge([
            'notification_log_id' => $this->notificationLog->id,
            'channel_id' => $this->channel->id,
            'status' => $status,
        ], $extra));

        // Manually set created_at via query builder since it's not in $fillable
        DB::table('notification_delivery_logs')
            ->where('id', $log->id)
            ->update(['created_at' => now()->subHours($hoursAgo)]);

        return $log->refresh();
    }

    public function test_marks_stale_pending_deliveries_as_failed(): void
    {
        $stale = $this->createDeliveryLog(DeliveryStatus::Pending, 25);

        $job = new CleanStaleDeliveriesJob;
        $job->handle();

        $stale->refresh();
        $this->assertSame(DeliveryStatus::Failed, $stale->status);
        $this->assertStringContainsString('stale delivery', $stale->error_message);
        $this->assertNotNull($stale->failed_at);
    }

    public function test_does_not_touch_recent_pending_deliveries(): void
    {
        $recent = $this->createDeliveryLog(DeliveryStatus::Pending, 2);

        $job = new CleanStaleDeliveriesJob;
        $job->handle();

        $recent->refresh();
        $this->assertSame(DeliveryStatus::Pending, $recent->status);
    }

    public function test_does_not_touch_already_delivered(): void
    {
        $delivered = $this->createDeliveryLog(DeliveryStatus::Delivered, 30, [
            'delivered_at' => now()->subHours(30),
        ]);

        $job = new CleanStaleDeliveriesJob;
        $job->handle();

        $delivered->refresh();
        $this->assertSame(DeliveryStatus::Delivered, $delivered->status);
    }

    public function test_logs_info_when_stale_deliveries_found(): void
    {
        Log::spy();

        $this->createDeliveryLog(DeliveryStatus::Pending, 25);

        $job = new CleanStaleDeliveriesJob;
        $job->handle();

        Log::shouldHaveReceived('info')
            ->withArgs(fn ($message, $context) => $message === 'Cleaned stale deliveries' && $context['count'] === 1)
            ->once();
    }

    public function test_does_not_log_when_no_stale_deliveries(): void
    {
        Log::spy();

        $job = new CleanStaleDeliveriesJob;
        $job->handle();

        Log::shouldNotHaveReceived('info');
    }

    public function test_job_is_queueable(): void
    {
        $job = new CleanStaleDeliveriesJob;

        $this->assertInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class, $job);
    }
}
