<?php

namespace Aicl\Tests\Feature;

use Aicl\Models\NotificationLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationLogModelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'Aicl\Database\Seeders\SettingsSeeder']);
    }

    // ─── Relationships ────────────────────────────────────────

    public function test_notifiable_relationship(): void
    {
        $user = User::factory()->create();

        $log = NotificationLog::create([
            'type' => 'App\\Notifications\\TestNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'channels' => ['database'],
            'channel_status' => ['database' => 'sent'],
            'data' => ['message' => 'test'],
        ]);

        $this->assertInstanceOf(User::class, $log->notifiable);
        $this->assertEquals($user->id, $log->notifiable->id);
    }

    public function test_sender_relationship(): void
    {
        $user = User::factory()->create();
        $sender = User::factory()->create();

        $log = NotificationLog::create([
            'type' => 'App\\Notifications\\TestNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'sender_type' => User::class,
            'sender_id' => $sender->id,
            'channels' => ['database'],
            'channel_status' => ['database' => 'sent'],
            'data' => ['message' => 'test'],
        ]);

        $this->assertInstanceOf(User::class, $log->sender);
        $this->assertEquals($sender->id, $log->sender->id);
    }

    public function test_sender_is_null_for_system_notifications(): void
    {
        $user = User::factory()->create();

        $log = NotificationLog::create([
            'type' => 'App\\Notifications\\TestNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'channels' => ['database'],
            'channel_status' => ['database' => 'sent'],
            'data' => ['message' => 'test'],
        ]);

        $this->assertNull($log->sender);
    }

    // ─── Casts ─────────────────────────────────────────────────

    public function test_channels_is_cast_to_array(): void
    {
        $user = User::factory()->create();

        $log = NotificationLog::create([
            'type' => 'App\\Notifications\\TestNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'channels' => ['database', 'mail'],
            'channel_status' => ['database' => 'sent', 'mail' => 'sent'],
            'data' => ['message' => 'test'],
        ]);

        $fresh = $log->fresh();
        $this->assertIsArray($fresh->channels);
        $this->assertCount(2, $fresh->channels);
    }

    public function test_channel_status_is_cast_to_array(): void
    {
        $user = User::factory()->create();

        $log = NotificationLog::create([
            'type' => 'App\\Notifications\\TestNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'channels' => ['database'],
            'channel_status' => ['database' => 'sent'],
            'data' => ['message' => 'test'],
        ]);

        $fresh = $log->fresh();
        $this->assertIsArray($fresh->channel_status);
        $this->assertEquals('sent', $fresh->channel_status['database']);
    }

    public function test_data_is_cast_to_array(): void
    {
        $user = User::factory()->create();

        $log = NotificationLog::create([
            'type' => 'App\\Notifications\\TestNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'channels' => ['database'],
            'channel_status' => ['database' => 'sent'],
            'data' => ['message' => 'test', 'nested' => ['key' => 'value']],
        ]);

        $fresh = $log->fresh();
        $this->assertIsArray($fresh->data);
        $this->assertEquals('test', $fresh->data['message']);
        $this->assertEquals('value', $fresh->data['nested']['key']);
    }

    public function test_read_at_is_cast_to_datetime(): void
    {
        $user = User::factory()->create();

        $log = NotificationLog::create([
            'type' => 'App\\Notifications\\TestNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'channels' => ['database'],
            'channel_status' => ['database' => 'sent'],
            'data' => ['message' => 'test'],
            'read_at' => now(),
        ]);

        $fresh = $log->fresh();
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $fresh->read_at);
    }

    // ─── markAsRead / markAsUnread ─────────────────────────────

    public function test_mark_as_read_sets_read_at(): void
    {
        $user = User::factory()->create();

        $log = NotificationLog::create([
            'type' => 'App\\Notifications\\TestNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'channels' => ['database'],
            'channel_status' => ['database' => 'sent'],
            'data' => ['message' => 'test'],
        ]);

        $this->assertNull($log->read_at);

        $log->markAsRead();
        $log->refresh();

        $this->assertNotNull($log->read_at);
    }

    public function test_mark_as_read_is_idempotent(): void
    {
        $user = User::factory()->create();

        $log = NotificationLog::create([
            'type' => 'App\\Notifications\\TestNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'channels' => ['database'],
            'channel_status' => ['database' => 'sent'],
            'data' => ['message' => 'test'],
        ]);

        $log->markAsRead();
        $log->refresh();
        $firstReadAt = $log->read_at->toDateTimeString();

        // Call again — should not update
        $log->markAsRead();
        $log->refresh();

        $this->assertEquals($firstReadAt, $log->read_at->toDateTimeString());
    }

    public function test_mark_as_unread_clears_read_at(): void
    {
        $user = User::factory()->create();

        $log = NotificationLog::create([
            'type' => 'App\\Notifications\\TestNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'channels' => ['database'],
            'channel_status' => ['database' => 'sent'],
            'data' => ['message' => 'test'],
            'read_at' => now(),
        ]);

        $this->assertNotNull($log->read_at);

        $log->markAsUnread();
        $log->refresh();

        $this->assertNull($log->read_at);
    }

    public function test_mark_as_unread_is_idempotent(): void
    {
        $user = User::factory()->create();

        $log = NotificationLog::create([
            'type' => 'App\\Notifications\\TestNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'channels' => ['database'],
            'channel_status' => ['database' => 'sent'],
            'data' => ['message' => 'test'],
        ]);

        // Already unread — calling markAsUnread should be a no-op
        $log->markAsUnread();
        $log->refresh();

        $this->assertNull($log->read_at);
    }

    // ─── Scopes ────────────────────────────────────────────────

    public function test_scope_for_user(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        NotificationLog::create([
            'type' => 'App\\Notifications\\TestNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => $user1->id,
            'channels' => ['database'],
            'channel_status' => ['database' => 'sent'],
            'data' => ['message' => 'for user1'],
        ]);

        NotificationLog::create([
            'type' => 'App\\Notifications\\TestNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => $user2->id,
            'channels' => ['database'],
            'channel_status' => ['database' => 'sent'],
            'data' => ['message' => 'for user2'],
        ]);

        $user1Logs = NotificationLog::forUser($user1)->get();
        $this->assertCount(1, $user1Logs);
        $this->assertEquals('for user1', $user1Logs->first()->data['message']);
    }

    public function test_scope_of_type(): void
    {
        $user = User::factory()->create();

        NotificationLog::create([
            'type' => 'App\\Notifications\\TypeA',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'channels' => ['database'],
            'channel_status' => ['database' => 'sent'],
            'data' => [],
        ]);

        NotificationLog::create([
            'type' => 'App\\Notifications\\TypeB',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'channels' => ['database'],
            'channel_status' => ['database' => 'sent'],
            'data' => [],
        ]);

        $typeALogs = NotificationLog::ofType('App\\Notifications\\TypeA')->get();
        $this->assertCount(1, $typeALogs);
    }

    public function test_scope_unread(): void
    {
        $user = User::factory()->create();

        NotificationLog::create([
            'type' => 'App\\Notifications\\TestNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'channels' => ['database'],
            'channel_status' => ['database' => 'sent'],
            'data' => [],
            'read_at' => now(),
        ]);

        NotificationLog::create([
            'type' => 'App\\Notifications\\TestNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'channels' => ['database'],
            'channel_status' => ['database' => 'sent'],
            'data' => [],
        ]);

        $unread = NotificationLog::forUser($user)->unread()->get();
        $this->assertCount(1, $unread);
        $this->assertNull($unread->first()->read_at);
    }

    public function test_scope_failed(): void
    {
        $user = User::factory()->create();

        NotificationLog::create([
            'type' => 'App\\Notifications\\TestNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'channels' => ['database', 'mail'],
            'channel_status' => ['database' => 'sent', 'mail' => 'sent'],
            'data' => [],
        ]);

        NotificationLog::create([
            'type' => 'App\\Notifications\\TestNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'channels' => ['database', 'mail'],
            'channel_status' => ['database' => 'sent', 'mail' => 'failed'],
            'data' => [],
        ]);

        $failed = NotificationLog::forUser($user)->failed()->get();
        $this->assertCount(1, $failed);
        $this->assertEquals('failed', $failed->first()->channel_status['mail']);
    }

    public function test_scopes_can_be_chained(): void
    {
        $user = User::factory()->create();

        NotificationLog::create([
            'type' => 'App\\Notifications\\TypeA',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'channels' => ['database'],
            'channel_status' => ['database' => 'sent'],
            'data' => [],
        ]);

        NotificationLog::create([
            'type' => 'App\\Notifications\\TypeA',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'channels' => ['database'],
            'channel_status' => ['database' => 'sent'],
            'data' => [],
            'read_at' => now(),
        ]);

        $results = NotificationLog::forUser($user)
            ->ofType('App\\Notifications\\TypeA')
            ->unread()
            ->get();

        $this->assertCount(1, $results);
    }

    // ─── getTypeLabelAttribute ─────────────────────────────────

    public function test_type_label_extracts_class_basename(): void
    {
        $user = User::factory()->create();

        $log = NotificationLog::create([
            'type' => 'App\\Notifications\\ProjectCreatedNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'channels' => ['database'],
            'channel_status' => ['database' => 'sent'],
            'data' => [],
        ]);

        $this->assertEquals('Project Created', $log->type_label);
    }

    public function test_type_label_handles_type_without_notification_suffix(): void
    {
        $user = User::factory()->create();

        $log = NotificationLog::create([
            'type' => 'App\\Notifications\\WelcomeMessage',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'channels' => ['database'],
            'channel_status' => ['database' => 'sent'],
            'data' => [],
        ]);

        $this->assertEquals('Welcome Message', $log->type_label);
    }

    public function test_type_label_returns_unknown_for_null_type(): void
    {
        $log = new NotificationLog;
        $log->type = null;

        $this->assertEquals('Unknown', $log->type_label);
    }

    public function test_type_label_returns_unknown_for_empty_type(): void
    {
        $log = new NotificationLog;
        $log->type = '';

        $this->assertEquals('Unknown', $log->type_label);
    }

    // ─── UUID ──────────────────────────────────────────────────

    public function test_uses_uuid_primary_key(): void
    {
        $user = User::factory()->create();

        $log = NotificationLog::create([
            'type' => 'App\\Notifications\\TestNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'channels' => ['database'],
            'channel_status' => ['database' => 'sent'],
            'data' => [],
        ]);

        $this->assertNotNull($log->id);
        $this->assertIsString($log->id);
        // UUID format: 8-4-4-4-12
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $log->id);
    }
}
