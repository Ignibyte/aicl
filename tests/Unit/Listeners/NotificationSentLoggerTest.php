<?php

namespace Aicl\Tests\Unit\Listeners;

use Aicl\Listeners\NotificationSentLogger;
use Aicl\Models\NotificationLog;
use Aicl\Notifications\BaseNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Notifications\Notification;
use Tests\TestCase;

class NotificationSentLoggerTest extends TestCase
{
    use RefreshDatabase;

    public function test_logger_creates_notification_log(): void
    {
        $user = User::factory()->create();

        $notification = new class extends Notification
        {
            /** @phpstan-ignore-next-line */
            public function toArray(object $notifiable): array
            {
                return ['title' => 'Test', 'body' => 'Test body'];
            }
        };

        $event = new NotificationSent($user, $notification, 'database');

        $logger = new NotificationSentLogger;
        $logger->handle($event);

        $this->assertDatabaseHas('notification_logs', [
            'notifiable_id' => $user->id,
        ]);

        $log = NotificationLog::where('notifiable_id', $user->id)->first();
        /** @phpstan-ignore-next-line */
        $this->assertEquals(['database'], $log->channels);
        /** @phpstan-ignore-next-line */
        $this->assertEquals(['database' => 'sent'], $log->channel_status);
    }

    public function test_logger_skips_base_notification_instances(): void
    {
        $user = User::factory()->create();

        $notification = new class extends BaseNotification
        {
            public function toDatabase(object $notifiable): array
            {
                return ['title' => 'Test', 'body' => 'Test'];
            }
        };

        $event = new NotificationSent($user, $notification, 'database');

        $logger = new NotificationSentLogger;
        $logger->handle($event);

        // Should NOT create a log since BaseNotification is already logged by NotificationDispatcher
        $this->assertEquals(0, NotificationLog::where('notifiable_id', $user->id)->count());
    }

    public function test_logger_skips_non_model_notifiables(): void
    {
        $notifiable = new class
        {
            public function getKey(): int
            {
                return 1;
            }
        };

        $notification = new class extends Notification
        {
            /** @phpstan-ignore-next-line */
            public function toArray(object $notifiable): array
            {
                return ['test' => true];
            }
        };

        $event = new NotificationSent($notifiable, $notification, 'database');

        $logger = new NotificationSentLogger;
        $logger->handle($event);

        $this->assertEquals(0, NotificationLog::count());
    }

    public function test_logger_extracts_data_from_to_array(): void
    {
        $user = User::factory()->create();

        $notification = new class extends Notification
        {
            /** @phpstan-ignore-next-line */
            public function toArray(object $notifiable): array
            {
                return ['title' => 'Custom Title', 'body' => 'Custom Body'];
            }
        };

        $event = new NotificationSent($user, $notification, 'mail');

        $logger = new NotificationSentLogger;
        $logger->handle($event);

        $log = NotificationLog::where('notifiable_id', $user->id)->first();

        /** @phpstan-ignore-next-line */
        $this->assertEquals('Custom Title', $log->data['title']);
        /** @phpstan-ignore-next-line */
        $this->assertEquals('Custom Body', $log->data['body']);
    }

    public function test_logger_falls_back_to_class_name_when_no_data_methods(): void
    {
        $user = User::factory()->create();

        $notification = new class extends Notification {};

        $event = new NotificationSent($user, $notification, 'database');

        $logger = new NotificationSentLogger;
        $logger->handle($event);

        $log = NotificationLog::where('notifiable_id', $user->id)->first();

        /** @phpstan-ignore-next-line */
        $this->assertArrayHasKey('type', $log->data);
    }

    public function test_logger_records_correct_channel(): void
    {
        $user = User::factory()->create();

        $notification = new class extends Notification
        {
            /** @phpstan-ignore-next-line */
            public function toArray(object $notifiable): array
            {
                return ['test' => true];
            }
        };

        $event = new NotificationSent($user, $notification, 'mail');

        $logger = new NotificationSentLogger;
        $logger->handle($event);

        $log = NotificationLog::where('notifiable_id', $user->id)->first();

        /** @phpstan-ignore-next-line */
        $this->assertEquals(['mail'], $log->channels);
        /** @phpstan-ignore-next-line */
        $this->assertEquals(['mail' => 'sent'], $log->channel_status);
    }
}
