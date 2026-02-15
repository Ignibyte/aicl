<?php

namespace Aicl\Tests\Unit\Events;

use Aicl\Events\DomainEvent;
use Aicl\Events\EntityCreated;
use Aicl\Events\EntityCreating;
use Aicl\Events\EntityDeleted;
use Aicl\Events\EntityDeleting;
use Aicl\Events\EntityUpdated;
use Aicl\Events\EntityUpdating;
use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Tests\TestCase;

class EntityLifecycleBroadcastTest extends TestCase
{
    public function test_entity_created_extends_domain_event(): void
    {
        $user = User::factory()->make(['id' => 1]);
        $event = new EntityCreated($user);

        $this->assertInstanceOf(DomainEvent::class, $event);
        $this->assertInstanceOf(ShouldBroadcast::class, $event);
    }

    public function test_entity_updated_extends_domain_event(): void
    {
        $user = User::factory()->make(['id' => 1]);
        $event = new EntityUpdated($user);

        $this->assertInstanceOf(DomainEvent::class, $event);
        $this->assertInstanceOf(ShouldBroadcast::class, $event);
    }

    public function test_entity_deleted_extends_domain_event(): void
    {
        $user = User::factory()->make(['id' => 1]);
        $event = new EntityDeleted($user);

        $this->assertInstanceOf(DomainEvent::class, $event);
        $this->assertInstanceOf(ShouldBroadcast::class, $event);
    }

    public function test_pre_action_events_do_not_broadcast(): void
    {
        $this->assertFalse(is_subclass_of(EntityCreating::class, ShouldBroadcast::class));
        $this->assertFalse(is_subclass_of(EntityUpdating::class, ShouldBroadcast::class));
        $this->assertFalse(is_subclass_of(EntityDeleting::class, ShouldBroadcast::class));
    }

    public function test_pre_action_events_do_not_extend_domain_event(): void
    {
        $this->assertFalse(is_subclass_of(EntityCreating::class, DomainEvent::class));
        $this->assertFalse(is_subclass_of(EntityUpdating::class, DomainEvent::class));
        $this->assertFalse(is_subclass_of(EntityDeleting::class, DomainEvent::class));
    }

    public function test_entity_created_broadcast_payload_includes_metadata(): void
    {
        $user = User::factory()->make(['id' => 42]);
        $event = new EntityCreated($user);

        $payload = $event->broadcastWith();

        $this->assertArrayHasKey('eventId', $payload);
        $this->assertArrayHasKey('eventType', $payload);
        $this->assertArrayHasKey('occurredAt', $payload);
        $this->assertSame('entity.created', $payload['eventType']);
        $this->assertArrayHasKey('action', $payload);
        $this->assertSame('created', $payload['action']);
    }

    public function test_entity_updated_broadcast_payload_includes_metadata(): void
    {
        $user = User::factory()->make(['id' => 42]);
        $event = new EntityUpdated($user);

        $payload = $event->broadcastWith();

        $this->assertArrayHasKey('eventId', $payload);
        $this->assertArrayHasKey('eventType', $payload);
        $this->assertArrayHasKey('occurredAt', $payload);
        $this->assertSame('entity.updated', $payload['eventType']);
    }

    public function test_entity_deleted_broadcast_payload_includes_metadata(): void
    {
        $user = User::factory()->make(['id' => 42]);
        $event = new EntityDeleted($user);

        $payload = $event->broadcastWith();

        $this->assertArrayHasKey('eventId', $payload);
        $this->assertArrayHasKey('eventType', $payload);
        $this->assertArrayHasKey('occurredAt', $payload);
        $this->assertSame('entity.deleted', $payload['eventType']);
    }

    public function test_entity_created_broadcast_as_returns_event_type(): void
    {
        $user = User::factory()->make(['id' => 1]);
        $event = new EntityCreated($user);

        $this->assertSame('entity.created', $event->broadcastAs());
    }

    public function test_entity_created_broadcasts_on_dashboard_and_entity_channels(): void
    {
        $user = User::factory()->make(['id' => 42]);
        $user->exists = true;
        $event = new EntityCreated($user);

        $channels = $event->broadcastOn();

        $this->assertCount(2, $channels);
        $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
        $this->assertInstanceOf(PrivateChannel::class, $channels[1]);
    }

    public function test_entity_deleted_broadcasts_on_dashboard_and_entity_channels(): void
    {
        $user = User::factory()->make(['id' => 42]);
        $event = new EntityDeleted($user);

        $channels = $event->broadcastOn();

        $this->assertCount(2, $channels);
        $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
        $this->assertInstanceOf(PrivateChannel::class, $channels[1]);
    }

    public function test_entity_deleted_stores_scalar_entity_info(): void
    {
        $user = User::factory()->make(['id' => 42]);
        $event = new EntityDeleted($user);

        $this->assertSame(42, $event->entityId);
        $this->assertSame('User', $event->entityType);
        $this->assertSame(User::class, $event->entityClass);
    }

    public function test_entity_deleted_overrides_entity_type_and_id_for_persistence(): void
    {
        $user = User::factory()->make(['id' => 42]);
        $event = new EntityDeleted($user);

        $this->assertSame(User::class, $event->getEntityType());
        $this->assertSame(42, $event->getEntityId());
    }
}
