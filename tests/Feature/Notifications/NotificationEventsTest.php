<?php

namespace Aicl\Tests\Feature\Notifications;

use Aicl\Database\Seeders\RoleSeeder;
use Aicl\Events\EntityCreated;
use Aicl\Events\EntityDeleted;
use Aicl\Events\EntityUpdated;
use Aicl\Models\NotificationLog;
use Aicl\Notifications\BaseNotification;
use Aicl\Notifications\Events\NotificationDispatched;
use Aicl\Notifications\Events\NotificationSending;
use App\Models\User;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NotificationEventsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        Event::fake([
            EntityCreated::class,
            EntityUpdated::class,
            EntityDeleted::class,
        ]);
    }

    // ─── NotificationSending Event ────────────────────────────

    public function test_notification_sending_event_can_be_constructed(): void
    {
        $notification = $this->createTestNotification();
        $user = User::factory()->create();
        $sender = User::factory()->create();

        $event = new NotificationSending($notification, $user, $sender);

        $this->assertSame($notification, $event->notification);
        $this->assertSame($user, $event->notifiable);
        $this->assertSame($sender, $event->sender);
        $this->assertFalse($event->cancelled);
    }

    public function test_notification_sending_event_sender_is_optional(): void
    {
        $notification = $this->createTestNotification();
        $user = User::factory()->create();

        $event = new NotificationSending($notification, $user);

        $this->assertNull($event->sender);
    }

    public function test_notification_sending_event_is_cancellable(): void
    {
        $notification = $this->createTestNotification();
        $user = User::factory()->create();

        $event = new NotificationSending($notification, $user);

        $this->assertFalse($event->cancelled);

        $event->cancel();

        $this->assertTrue($event->cancelled);
    }

    public function test_notification_sending_cancel_is_idempotent(): void
    {
        $notification = $this->createTestNotification();
        $user = User::factory()->create();

        $event = new NotificationSending($notification, $user);

        $event->cancel();
        $event->cancel();

        $this->assertTrue($event->cancelled);
    }

    public function test_notification_sending_properties_are_readonly(): void
    {
        $reflection = new \ReflectionClass(NotificationSending::class);

        $this->assertTrue($reflection->getProperty('notification')->isReadOnly());
        $this->assertTrue($reflection->getProperty('notifiable')->isReadOnly());
        $this->assertTrue($reflection->getProperty('sender')->isReadOnly());
    }

    public function test_notification_sending_cancelled_is_not_readonly(): void
    {
        $reflection = new \ReflectionClass(NotificationSending::class);

        // cancelled must be writable via cancel()
        $this->assertFalse($reflection->getProperty('cancelled')->isReadOnly());
    }

    // ─── NotificationDispatched Event ─────────────────────────

    public function test_notification_dispatched_event_can_be_constructed(): void
    {
        Notification::fake();

        $notification = $this->createTestNotification();
        $user = User::factory()->create();

        $log = NotificationLog::create([
            'type' => get_class($notification),
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'channels' => ['database'],
            'channel_status' => ['database' => 'sent'],
            'data' => ['title' => 'Test', 'body' => 'body'],
        ]);

        $event = new NotificationDispatched($notification, $user, $log);

        $this->assertSame($notification, $event->notification);
        $this->assertSame($user, $event->notifiable);
        $this->assertSame($log, $event->log);
    }

    public function test_notification_dispatched_properties_are_readonly(): void
    {
        $reflection = new \ReflectionClass(NotificationDispatched::class);

        $this->assertTrue($reflection->getProperty('notification')->isReadOnly());
        $this->assertTrue($reflection->getProperty('notifiable')->isReadOnly());
        $this->assertTrue($reflection->getProperty('log')->isReadOnly());
    }

    public function test_notification_dispatched_log_has_correct_type(): void
    {
        Notification::fake();

        $notification = $this->createTestNotification();
        $user = User::factory()->create();

        $log = NotificationLog::create([
            'type' => get_class($notification),
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'channels' => ['database', 'mail'],
            'channel_status' => ['database' => 'sent', 'mail' => 'sent'],
            'data' => ['title' => 'Test', 'body' => 'body'],
        ]);

        $event = new NotificationDispatched($notification, $user, $log);

        $this->assertSame(get_class($notification), $event->log->type);
        $this->assertContains('database', $event->log->channels);
        $this->assertContains('mail', $event->log->channels);
    }

    // ─── Event Integration ─────────────────────────────────────

    public function test_notification_sending_is_a_plain_event_class(): void
    {
        $reflection = new \ReflectionClass(NotificationSending::class);

        // It should not implement ShouldBroadcast or ShouldQueue
        $this->assertFalse($reflection->implementsInterface(ShouldBroadcast::class));
    }

    public function test_notification_dispatched_is_a_plain_event_class(): void
    {
        $reflection = new \ReflectionClass(NotificationDispatched::class);

        $this->assertFalse($reflection->implementsInterface(ShouldBroadcast::class));
    }

    /**
     * Create a concrete BaseNotification for testing.
     */
    private function createTestNotification(): BaseNotification
    {
        return new class extends BaseNotification
        {
            public function toDatabase(object $notifiable): array
            {
                return [
                    'title' => 'Test Event Notification',
                    'body' => 'Testing event integration',
                ];
            }
        };
    }
}
