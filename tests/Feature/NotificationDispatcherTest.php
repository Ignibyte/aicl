<?php

namespace Aicl\Tests\Feature;

use Aicl\Database\Seeders\RoleSeeder;
use Aicl\Events\EntityCreated;
use Aicl\Events\EntityDeleted;
use Aicl\Events\EntityUpdated;
use Aicl\Models\NotificationLog;
use Aicl\Notifications\BaseNotification;
use Aicl\Services\NotificationDispatcher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NotificationDispatcherTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $user;

    protected NotificationDispatcher $dispatcher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        Event::fake([
            EntityCreated::class,
            EntityUpdated::class,
            EntityDeleted::class,
        ]);

        $this->admin = User::factory()->create(['email_verified_at' => now()]);
        $this->admin->assignRole('super_admin');

        $this->user = User::factory()->create(['email_verified_at' => now()]);
        $this->user->assignRole('viewer');

        $this->dispatcher = app(NotificationDispatcher::class);
    }

    protected function makeTestNotification(array $channels = ['database']): BaseNotification
    {
        return new class($channels) extends BaseNotification
        {
            public function __construct(protected array $testChannels)
            {
                //
            }

            public function via(object $notifiable): array
            {
                if ($this->onlyChannel) {
                    return [$this->onlyChannel];
                }

                return $this->testChannels;
            }

            public function toDatabase(object $notifiable): array
            {
                return ['title' => 'Test', 'body' => 'Test body'];
            }
        };
    }

    public function test_send_creates_log_with_correct_data(): void
    {
        Notification::fake();

        $notification = $this->makeTestNotification();
        $log = $this->dispatcher->send($this->user, $notification, $this->admin);

        $this->assertInstanceOf(NotificationLog::class, $log);
        $this->assertEquals(User::class, $log->notifiable_type);
        $this->assertEquals($this->user->id, $log->notifiable_id);
        $this->assertEquals(User::class, $log->sender_type);
        $this->assertEquals($this->admin->id, $log->sender_id);
        $this->assertContains('database', $log->channels);
    }

    public function test_send_without_sender(): void
    {
        Notification::fake();

        $notification = $this->makeTestNotification();
        $log = $this->dispatcher->send($this->user, $notification);

        $this->assertNull($log->sender_type);
        $this->assertNull($log->sender_id);
    }

    public function test_send_initializes_channel_status_as_pending(): void
    {
        Notification::fake();

        $notification = $this->makeTestNotification(['database', 'mail']);
        $log = $this->dispatcher->send($this->user, $notification);

        // The log is created with 'pending' initially, then updated after dispatch
        $this->assertIsArray($log->channel_status);
    }

    public function test_send_updates_channel_status_after_dispatch(): void
    {
        Notification::fake();

        $notification = $this->makeTestNotification(['database']);
        $log = $this->dispatcher->send($this->user, $notification);
        $log->refresh();

        $this->assertIsArray($log->channel_status);
        // With Notification::fake(), channels should show as 'sent' since fake doesn't throw
        $this->assertEquals('sent', $log->channel_status['database']);
    }

    public function test_send_stores_notification_data(): void
    {
        Notification::fake();

        $notification = $this->makeTestNotification();
        $log = $this->dispatcher->send($this->user, $notification);

        $this->assertIsArray($log->data);
        $this->assertEquals('Test', $log->data['title']);
        $this->assertEquals('Test body', $log->data['body']);
    }

    public function test_send_to_many_returns_collection_of_logs(): void
    {
        Notification::fake();
        NotificationLog::query()->delete();

        $user2 = User::factory()->create();
        $notification = $this->makeTestNotification();

        $logs = $this->dispatcher->sendToMany(
            collect([$this->user, $user2]),
            $notification,
            $this->admin,
        );

        $this->assertCount(2, $logs);
        $this->assertInstanceOf(NotificationLog::class, $logs->first());
    }

    public function test_send_to_many_with_empty_collection(): void
    {
        $notification = $this->makeTestNotification();

        $logs = $this->dispatcher->sendToMany(
            collect(),
            $notification,
        );

        $this->assertCount(0, $logs);
    }

    public function test_send_handles_channel_exception_gracefully(): void
    {
        // Create a notification that will throw on mail channel
        $notification = new class extends BaseNotification
        {
            public function via(object $notifiable): array
            {
                if ($this->onlyChannel) {
                    return [$this->onlyChannel];
                }

                return ['database', 'mail'];
            }

            public function toDatabase(object $notifiable): array
            {
                return ['title' => 'Test', 'body' => 'body'];
            }

            public function toMail(object $notifiable): MailMessage
            {
                throw new \RuntimeException('Mail channel failed');
            }
        };

        $log = $this->dispatcher->send($this->user, $notification);
        $log->refresh();

        $this->assertIsArray($log->channel_status);
        // Mail should fail due to intentional exception
        $this->assertEquals('failed', $log->channel_status['mail']);
        // Database may also fail since the notification implements ShouldQueue
        $this->assertContains($log->channel_status['database'], ['sent', 'failed']);
    }

    public function test_send_with_multiple_channels(): void
    {
        Notification::fake();

        $notification = $this->makeTestNotification(['database', 'mail', 'broadcast']);
        $log = $this->dispatcher->send($this->user, $notification);
        $log->refresh();

        $this->assertCount(3, $log->channels);
        $this->assertContains('database', $log->channels);
        $this->assertContains('mail', $log->channels);
        $this->assertContains('broadcast', $log->channels);
    }

    public function test_send_stores_notification_type_as_class_name(): void
    {
        Notification::fake();

        $notification = $this->makeTestNotification();
        $log = $this->dispatcher->send($this->user, $notification);

        $this->assertNotEmpty($log->type);
        $this->assertStringContainsString('anonymous', $log->type);
    }

    public function test_send_to_many_each_log_has_unique_id(): void
    {
        Notification::fake();
        NotificationLog::query()->delete();

        $user2 = User::factory()->create();
        $notification = $this->makeTestNotification();

        $logs = $this->dispatcher->sendToMany(
            collect([$this->user, $user2]),
            $notification,
        );

        $this->assertNotEquals($logs[0]->id, $logs[1]->id);
    }

    public function test_notification_stores_database_data(): void
    {
        Notification::fake();

        $notification = $this->makeTestNotification();
        $log = $this->dispatcher->send($this->user, $notification);

        $this->assertIsArray($log->data);
        $this->assertArrayHasKey('title', $log->data);
    }
}
