<?php

namespace Aicl\Tests\Feature\Notifications;

use Aicl\Models\NotificationLog;
use Aicl\Notifications\Enums\ChannelType;
use Aicl\Notifications\Enums\DeliveryStatus;
use Aicl\Notifications\Models\NotificationChannel;
use Aicl\Notifications\Models\NotificationDeliveryLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationDeliveryLogModelTest extends TestCase
{
    use RefreshDatabase;

    private NotificationChannel $channel;

    private NotificationLog $notificationLog;

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
    }

    private function createDeliveryLog(array $attributes = []): NotificationDeliveryLog
    {
        return NotificationDeliveryLog::create(array_merge([
            'notification_log_id' => $this->notificationLog->id,
            'channel_id' => $this->channel->id,
            'status' => DeliveryStatus::Pending,
            'payload' => ['title' => 'Test', 'body' => 'body'],
        ], $attributes));
    }

    // ─── Creation & Attributes ─────────────────────────────────

    public function test_create_delivery_log(): void
    {
        $log = $this->createDeliveryLog();

        $this->assertNotNull($log->id);
        $this->assertSame($this->notificationLog->id, $log->notification_log_id);
        $this->assertSame($this->channel->id, $log->channel_id);
    }

    public function test_uses_uuid_primary_key(): void
    {
        $log = $this->createDeliveryLog();

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $log->id
        );
    }

    public function test_created_at_is_auto_set(): void
    {
        $log = $this->createDeliveryLog();

        $this->assertNotNull($log->created_at);
    }

    public function test_default_attempt_count_is_zero(): void
    {
        $log = $this->createDeliveryLog();

        $this->assertSame(0, $log->attempt_count);
    }

    // ─── Enum Casts ────────────────────────────────────────────

    public function test_status_is_cast_to_delivery_status_enum(): void
    {
        $log = $this->createDeliveryLog(['status' => DeliveryStatus::Sent]);
        $fresh = $log->fresh();

        $this->assertInstanceOf(DeliveryStatus::class, $fresh->status);
        $this->assertSame(DeliveryStatus::Sent, $fresh->status);
    }

    public function test_all_status_values_can_be_stored(): void
    {
        foreach (DeliveryStatus::cases() as $status) {
            $log = $this->createDeliveryLog(['status' => $status]);
            $fresh = $log->fresh();

            $this->assertSame($status, $fresh->status, "Status {$status->value} should be stored correctly");
        }
    }

    // ─── JSON Casts ────────────────────────────────────────────

    public function test_payload_is_cast_to_array(): void
    {
        $payload = ['title' => 'Alert', 'body' => 'Something happened', 'action_url' => 'https://example.com'];
        $log = $this->createDeliveryLog(['payload' => $payload]);
        $fresh = $log->fresh();

        $this->assertIsArray($fresh->payload);
        $this->assertSame('Alert', $fresh->payload['title']);
    }

    public function test_response_is_cast_to_array(): void
    {
        $response = ['status' => 200, 'body' => 'ok'];
        $log = $this->createDeliveryLog(['response' => $response]);
        $fresh = $log->fresh();

        $this->assertIsArray($fresh->response);
        $this->assertSame(200, $fresh->response['status']);
    }

    public function test_payload_can_be_null(): void
    {
        $log = $this->createDeliveryLog(['payload' => null]);
        $fresh = $log->fresh();

        $this->assertNull($fresh->payload);
    }

    // ─── DateTime Casts ────────────────────────────────────────

    public function test_sent_at_is_cast_to_datetime(): void
    {
        $log = $this->createDeliveryLog(['sent_at' => now()]);
        $fresh = $log->fresh();

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $fresh->sent_at);
    }

    public function test_delivered_at_is_cast_to_datetime(): void
    {
        $log = $this->createDeliveryLog(['delivered_at' => now()]);
        $fresh = $log->fresh();

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $fresh->delivered_at);
    }

    public function test_failed_at_is_cast_to_datetime(): void
    {
        $log = $this->createDeliveryLog(['failed_at' => now()]);
        $fresh = $log->fresh();

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $fresh->failed_at);
    }

    public function test_next_retry_at_is_cast_to_datetime(): void
    {
        $log = $this->createDeliveryLog(['next_retry_at' => now()->addMinutes(5)]);
        $fresh = $log->fresh();

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $fresh->next_retry_at);
    }

    // ─── Scopes ────────────────────────────────────────────────

    public function test_scope_pending(): void
    {
        $this->createDeliveryLog(['status' => DeliveryStatus::Pending]);
        $this->createDeliveryLog(['status' => DeliveryStatus::Sent]);
        $this->createDeliveryLog(['status' => DeliveryStatus::Failed]);

        $pending = NotificationDeliveryLog::pending()->get();

        $this->assertCount(1, $pending);
        $this->assertSame(DeliveryStatus::Pending, $pending->first()->status);
    }

    public function test_scope_failed(): void
    {
        $this->createDeliveryLog(['status' => DeliveryStatus::Pending]);
        $this->createDeliveryLog(['status' => DeliveryStatus::Failed]);
        $this->createDeliveryLog(['status' => DeliveryStatus::Delivered]);

        $failed = NotificationDeliveryLog::failed()->get();

        $this->assertCount(1, $failed);
        $this->assertSame(DeliveryStatus::Failed, $failed->first()->status);
    }

    public function test_scope_retryable(): void
    {
        // Not retryable: pending
        $this->createDeliveryLog(['status' => DeliveryStatus::Pending]);

        // Not retryable: failed but no next_retry_at
        $this->createDeliveryLog([
            'status' => DeliveryStatus::Failed,
            'attempt_count' => 1,
        ]);

        // Not retryable: failed with next_retry_at in the future
        $this->createDeliveryLog([
            'status' => DeliveryStatus::Failed,
            'attempt_count' => 1,
            'next_retry_at' => now()->addHour(),
        ]);

        // Not retryable: max attempts exceeded
        $this->createDeliveryLog([
            'status' => DeliveryStatus::Failed,
            'attempt_count' => 5,
            'next_retry_at' => now()->subMinute(),
        ]);

        // Retryable: failed, next_retry_at in past, under max attempts
        $this->createDeliveryLog([
            'status' => DeliveryStatus::Failed,
            'attempt_count' => 2,
            'next_retry_at' => now()->subMinute(),
        ]);

        $retryable = NotificationDeliveryLog::retryable()->get();

        $this->assertCount(1, $retryable);
        $this->assertSame(2, $retryable->first()->attempt_count);
    }

    public function test_scope_for_channel(): void
    {
        $otherChannel = NotificationChannel::create([
            'name' => 'Other Channel',
            'slug' => 'other-channel',
            'type' => ChannelType::Email,
            'config' => ['to' => ['admin@example.com']],
            'is_active' => true,
        ]);

        $this->createDeliveryLog(['channel_id' => $this->channel->id]);
        $this->createDeliveryLog(['channel_id' => $otherChannel->id]);

        $forChannel = NotificationDeliveryLog::forChannel($this->channel)->get();

        $this->assertCount(1, $forChannel);
        $this->assertSame($this->channel->id, $forChannel->first()->channel_id);
    }

    // ─── Relationships ─────────────────────────────────────────

    public function test_notification_log_relationship(): void
    {
        $log = $this->createDeliveryLog();

        $this->assertInstanceOf(NotificationLog::class, $log->notificationLog);
        $this->assertSame($this->notificationLog->id, $log->notificationLog->id);
    }

    public function test_channel_relationship(): void
    {
        $log = $this->createDeliveryLog();

        $this->assertInstanceOf(NotificationChannel::class, $log->channel);
        $this->assertSame($this->channel->id, $log->channel->id);
    }

    // ─── Error Message ─────────────────────────────────────────

    public function test_error_message_can_be_stored(): void
    {
        $log = $this->createDeliveryLog([
            'status' => DeliveryStatus::Failed,
            'error_message' => 'Webhook returned 500: Internal Server Error',
        ]);

        $fresh = $log->fresh();
        $this->assertSame('Webhook returned 500: Internal Server Error', $fresh->error_message);
    }

    public function test_error_message_is_null_by_default(): void
    {
        $log = $this->createDeliveryLog();
        $fresh = $log->fresh();

        $this->assertNull($fresh->error_message);
    }
}
