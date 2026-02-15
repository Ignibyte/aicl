<?php

namespace Aicl\Tests\Feature\Notifications;

use Aicl\Models\NotificationLog;
use Aicl\Notifications\Contracts\NotificationChannelDriver;
use Aicl\Notifications\DriverRegistry;
use Aicl\Notifications\DriverResult;
use Aicl\Notifications\Enums\ChannelType;
use Aicl\Notifications\Enums\DeliveryStatus;
use Aicl\Notifications\Jobs\RetryNotificationDelivery;
use Aicl\Notifications\Models\NotificationChannel;
use Aicl\Notifications\Models\NotificationDeliveryLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RetryNotificationDeliveryJobTest extends TestCase
{
    use RefreshDatabase;

    private NotificationChannel $channel;

    private NotificationLog $notificationLog;

    private DriverRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->channel = NotificationChannel::create([
            'name' => 'Test Channel',
            'slug' => 'test-channel',
            'type' => ChannelType::Slack,
            'config' => ['webhook_url' => 'https://hooks.slack.com/test'],
            'is_active' => true,
        ]);

        $user = User::factory()->create();

        $this->notificationLog = NotificationLog::create([
            'type' => 'Test\\Notification',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'channels' => ['slack'],
            'channel_status' => ['slack' => 'pending'],
            'data' => ['message' => 'test'],
        ]);

        $this->registry = new DriverRegistry($this->app);
    }

    private function createDeliveryLog(array $attributes = []): NotificationDeliveryLog
    {
        return NotificationDeliveryLog::create(array_merge([
            'notification_log_id' => $this->notificationLog->id,
            'channel_id' => $this->channel->id,
            'status' => DeliveryStatus::Failed,
            'attempt_count' => 1,
            'payload' => ['title' => 'Retry Test', 'body' => 'body'],
            'error_message' => 'Previous attempt failed',
        ], $attributes));
    }

    private function registerSuccessDriver(): void
    {
        $this->registry->register(ChannelType::Slack, SuccessfulTestDriver::class);
        $this->app->instance(DriverRegistry::class, $this->registry);
    }

    private function registerFailureDriver(bool $retryable = true): void
    {
        if ($retryable) {
            $this->registry->register(ChannelType::Slack, RetryableFailureTestDriver::class);
        } else {
            $this->registry->register(ChannelType::Slack, NonRetryableFailureTestDriver::class);
        }
        $this->app->instance(DriverRegistry::class, $this->registry);
    }

    public function test_successful_delivery_updates_status_to_delivered(): void
    {
        $this->registerSuccessDriver();

        $log = $this->createDeliveryLog(['attempt_count' => 1]);

        $job = new RetryNotificationDelivery($log->id);
        $job->handle($this->registry);

        $log->refresh();

        $this->assertSame(DeliveryStatus::Delivered, $log->status);
        $this->assertNotNull($log->delivered_at);
        $this->assertSame(2, $log->attempt_count);
    }

    public function test_successful_delivery_sets_sent_at_if_null(): void
    {
        $this->registerSuccessDriver();

        $log = $this->createDeliveryLog(['sent_at' => null]);

        $job = new RetryNotificationDelivery($log->id);
        $job->handle($this->registry);

        $log->refresh();

        $this->assertNotNull($log->sent_at);
    }

    public function test_successful_delivery_preserves_existing_sent_at(): void
    {
        $this->registerSuccessDriver();

        $sentAt = now()->subMinutes(10);
        $log = $this->createDeliveryLog(['sent_at' => $sentAt]);

        $job = new RetryNotificationDelivery($log->id);
        $job->handle($this->registry);

        $log->refresh();

        $this->assertSame(
            $sentAt->format('Y-m-d H:i:s'),
            $log->sent_at->format('Y-m-d H:i:s'),
        );
    }

    public function test_successful_delivery_stores_driver_response(): void
    {
        $this->registerSuccessDriver();

        $log = $this->createDeliveryLog();

        $job = new RetryNotificationDelivery($log->id);
        $job->handle($this->registry);

        $log->refresh();

        $this->assertIsArray($log->response);
        $this->assertSame('ok', $log->response['status']);
    }

    public function test_failed_delivery_with_retry_dispatches_new_job(): void
    {
        Queue::fake();
        $this->registerFailureDriver(retryable: true);

        $log = $this->createDeliveryLog(['attempt_count' => 1]);

        $job = new RetryNotificationDelivery($log->id);
        $job->handle($this->registry);

        $log->refresh();

        $this->assertSame(DeliveryStatus::Failed, $log->status);
        $this->assertSame(2, $log->attempt_count);
        $this->assertNotNull($log->next_retry_at);
        $this->assertNotNull($log->error_message);

        Queue::assertPushed(RetryNotificationDelivery::class, function ($pushedJob) use ($log) {
            return $pushedJob->deliveryLogId === $log->id;
        });
    }

    public function test_failed_delivery_non_retryable_does_not_dispatch_new_job(): void
    {
        Queue::fake();
        $this->registerFailureDriver(retryable: false);

        $log = $this->createDeliveryLog(['attempt_count' => 1]);

        $job = new RetryNotificationDelivery($log->id);
        $job->handle($this->registry);

        $log->refresh();

        $this->assertSame(DeliveryStatus::Failed, $log->status);
        $this->assertNotNull($log->failed_at);

        Queue::assertNotPushed(RetryNotificationDelivery::class);
    }

    public function test_max_attempts_exceeded_marks_as_permanently_failed(): void
    {
        $this->registerSuccessDriver(); // Driver would succeed, but shouldn't be called

        $maxAttempts = (int) config('aicl.notifications.retry.max_attempts', 5);

        $log = $this->createDeliveryLog(['attempt_count' => $maxAttempts]);

        $job = new RetryNotificationDelivery($log->id);
        $job->handle($this->registry);

        $log->refresh();

        $this->assertSame(DeliveryStatus::Failed, $log->status);
        $this->assertNotNull($log->failed_at);
        // Attempt count should NOT increase since the driver was not called
        $this->assertSame($maxAttempts, $log->attempt_count);
    }

    public function test_inactive_channel_marks_as_failed(): void
    {
        $this->registerSuccessDriver();

        $this->channel->update(['is_active' => false]);

        $log = $this->createDeliveryLog();

        $job = new RetryNotificationDelivery($log->id);
        $job->handle($this->registry);

        $log->refresh();

        $this->assertSame(DeliveryStatus::Failed, $log->status);
        $this->assertNotNull($log->failed_at);
        $this->assertStringContainsString('inactive', $log->error_message);
    }

    public function test_missing_delivery_log_exits_gracefully(): void
    {
        $this->registerSuccessDriver();

        $job = new RetryNotificationDelivery('nonexistent-uuid');
        $job->handle($this->registry);

        // No exception — just returns silently
        $this->assertTrue(true);
    }

    public function test_deleted_channel_cascades_to_delivery_log(): void
    {
        $this->registerSuccessDriver();

        $log = $this->createDeliveryLog();
        $logId = $log->id;

        // Cascade delete removes both channel and delivery log
        $this->channel->delete();

        $this->assertNull(NotificationDeliveryLog::find($logId));
    }

    public function test_job_has_single_try(): void
    {
        $job = new RetryNotificationDelivery('test-id');

        $this->assertSame(1, $job->tries);
    }

    public function test_job_implements_should_queue(): void
    {
        $job = new RetryNotificationDelivery('test-id');

        $this->assertInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class, $job);
    }

    public function test_attempt_count_increments_on_retry(): void
    {
        Queue::fake();
        $this->registerFailureDriver(retryable: true);

        $log = $this->createDeliveryLog(['attempt_count' => 3]);

        $job = new RetryNotificationDelivery($log->id);
        $job->handle($this->registry);

        $log->refresh();

        $this->assertSame(4, $log->attempt_count);
    }
}

class SuccessfulTestDriver implements NotificationChannelDriver
{
    public function send(NotificationChannel $channel, array $payload): DriverResult
    {
        return DriverResult::success(
            messageId: 'test-msg-id',
            response: ['status' => 'ok'],
        );
    }

    public function validateConfig(array $config): array
    {
        return [];
    }

    public function configSchema(): array
    {
        return [];
    }
}

class RetryableFailureTestDriver implements NotificationChannelDriver
{
    public function send(NotificationChannel $channel, array $payload): DriverResult
    {
        return DriverResult::failure(
            error: 'Server error: 500',
            retryable: true,
            response: ['status' => 500],
        );
    }

    public function validateConfig(array $config): array
    {
        return [];
    }

    public function configSchema(): array
    {
        return [];
    }
}

class NonRetryableFailureTestDriver implements NotificationChannelDriver
{
    public function send(NotificationChannel $channel, array $payload): DriverResult
    {
        return DriverResult::failure(
            error: 'Bad request: 400',
            retryable: false,
            response: ['status' => 400],
        );
    }

    public function validateConfig(array $config): array
    {
        return [];
    }

    public function configSchema(): array
    {
        return [];
    }
}
