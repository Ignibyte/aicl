<?php

namespace Aicl\Tests\Unit\Listeners;

use Aicl\Events\EntityCreated;
use Aicl\Events\EntityDeleted;
use Aicl\Events\EntityUpdated;
use Aicl\Listeners\EntityEventNotificationListener;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EntityEventNotificationListenerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'viewer', 'guard_name' => 'web']);
    }

    public function test_listener_implements_should_queue(): void
    {
        $listener = new EntityEventNotificationListener;

        $this->assertInstanceOf(ShouldQueue::class, $listener);
    }

    public function test_listener_has_handle_created_method(): void
    {
        $this->assertTrue((new \ReflectionClass(EntityEventNotificationListener::class))->hasMethod('handleCreated'));
    }

    public function test_listener_has_handle_updated_method(): void
    {
        $this->assertTrue((new \ReflectionClass(EntityEventNotificationListener::class))->hasMethod('handleUpdated'));
    }

    public function test_listener_has_handle_deleted_method(): void
    {
        $this->assertTrue((new \ReflectionClass(EntityEventNotificationListener::class))->hasMethod('handleDeleted'));
    }

    public function test_handle_created_creates_database_notification(): void
    {
        $actor = User::factory()->create();
        $actor->assignRole('super_admin');

        $recipient = User::factory()->create();
        $recipient->assignRole('super_admin');

        // Use User as our entity since Project model doesn't exist as a standalone class
        $entity = User::factory()->create(['name' => 'Test Entity']);

        $this->actingAs($actor);

        $listener = new EntityEventNotificationListener;
        $listener->handleCreated(new EntityCreated($entity));

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $recipient->id,
            'type' => 'Aicl\\Notifications\\EntityEventNotification',
        ]);
    }

    public function test_handle_created_excludes_actor_from_recipients(): void
    {
        $actor = User::factory()->create();
        $actor->assignRole('super_admin');

        // Use a different user as the entity
        $entity = User::factory()->create(['name' => 'Test Entity']);

        // Clear any notifications created during user factory setup
        DatabaseNotification::truncate();

        $this->actingAs($actor);

        $listener = new EntityEventNotificationListener;
        $listener->handleCreated(new EntityCreated($entity));

        // Actor should NOT receive their own notification
        $this->assertDatabaseMissing('notifications', [
            'notifiable_id' => $actor->id,
        ]);
    }

    public function test_handle_updated_creates_notification(): void
    {
        $actor = User::factory()->create();
        $actor->assignRole('super_admin');

        $recipient = User::factory()->create();
        $recipient->assignRole('super_admin');

        $entity = User::factory()->create(['name' => 'Test Entity']);

        // Clear notifications from user factory creation events
        DatabaseNotification::truncate();

        $this->actingAs($actor);

        $listener = new EntityEventNotificationListener;
        $listener->handleUpdated(new EntityUpdated($entity));

        $notification = DatabaseNotification::where('notifiable_id', $recipient->id)->first();

        $this->assertNotNull($notification);
        $this->assertEquals('updated', $notification->data['action']);
    }

    public function test_handle_deleted_creates_notification(): void
    {
        $actor = User::factory()->create();
        $actor->assignRole('super_admin');

        $recipient = User::factory()->create();
        $recipient->assignRole('super_admin');

        // EntityDeleted takes a Model in constructor
        $entity = User::factory()->create(['name' => 'Test Entity']);

        // Clear notifications from user factory creation events
        DatabaseNotification::truncate();

        $this->actingAs($actor);

        $listener = new EntityEventNotificationListener;
        $listener->handleDeleted(new EntityDeleted($entity));

        $notification = DatabaseNotification::where('notifiable_id', $recipient->id)->first();

        $this->assertNotNull($notification);
        $this->assertEquals('deleted', $notification->data['action']);
        $this->assertEquals('User', $notification->data['entity_type']);
    }

    public function test_notification_data_contains_required_fields(): void
    {
        $actor = User::factory()->create();
        $actor->assignRole('super_admin');

        $recipient = User::factory()->create();
        $recipient->assignRole('super_admin');

        $entity = User::factory()->create(['name' => 'Test Entity']);

        $this->actingAs($actor);

        $listener = new EntityEventNotificationListener;
        $listener->handleCreated(new EntityCreated($entity));

        $notification = DatabaseNotification::where('notifiable_id', $recipient->id)->first();

        /** @phpstan-ignore-next-line */
        $this->assertArrayHasKey('title', $notification->data);
        /** @phpstan-ignore-next-line */
        $this->assertArrayHasKey('body', $notification->data);
        /** @phpstan-ignore-next-line */
        $this->assertArrayHasKey('icon', $notification->data);
        /** @phpstan-ignore-next-line */
        $this->assertArrayHasKey('color', $notification->data);
        /** @phpstan-ignore-next-line */
        $this->assertArrayHasKey('entity_type', $notification->data);
        /** @phpstan-ignore-next-line */
        $this->assertArrayHasKey('entity_id', $notification->data);
        /** @phpstan-ignore-next-line */
        $this->assertArrayHasKey('action', $notification->data);
        /** @phpstan-ignore-next-line */
        $this->assertArrayHasKey('actor_id', $notification->data);
        /** @phpstan-ignore-next-line */
        $this->assertArrayHasKey('actor_name', $notification->data);
    }

    public function test_created_notification_has_correct_icon_and_color(): void
    {
        $actor = User::factory()->create();
        $actor->assignRole('super_admin');

        $recipient = User::factory()->create();
        $recipient->assignRole('super_admin');

        $entity = User::factory()->create(['name' => 'Test Entity']);

        $this->actingAs($actor);

        $listener = new EntityEventNotificationListener;
        $listener->handleCreated(new EntityCreated($entity));

        $notification = DatabaseNotification::where('notifiable_id', $recipient->id)->first();

        /** @phpstan-ignore-next-line */
        $this->assertEquals('heroicon-o-plus-circle', $notification->data['icon']);
        /** @phpstan-ignore-next-line */
        $this->assertEquals('success', $notification->data['color']);
    }

    public function test_no_notification_when_no_recipients(): void
    {
        // Actor without super_admin role, and no other super_admins
        $actor = User::factory()->create();
        $entity = User::factory()->create(['name' => 'Test Entity']);

        $this->actingAs($actor);

        $listener = new EntityEventNotificationListener;
        $listener->handleCreated(new EntityCreated($entity));

        $this->assertEquals(0, DatabaseNotification::count());
    }

    public function test_notification_log_is_created_alongside_notification(): void
    {
        $actor = User::factory()->create();
        $actor->assignRole('super_admin');

        $recipient = User::factory()->create();
        $recipient->assignRole('super_admin');

        $entity = User::factory()->create(['name' => 'Test Entity']);

        $this->actingAs($actor);

        $listener = new EntityEventNotificationListener;
        $listener->handleCreated(new EntityCreated($entity));

        $this->assertDatabaseHas('notification_logs', [
            'notifiable_id' => $recipient->id,
            'type' => 'Aicl\\Notifications\\EntityEventNotification',
        ]);
    }
}
