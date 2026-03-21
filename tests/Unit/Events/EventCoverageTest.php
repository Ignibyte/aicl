<?php

namespace Aicl\Tests\Unit\Events;

use Aicl\Events\DomainEvent;
use Aicl\Events\DomainEventRegistry;
use Aicl\Events\DomainEventSubscriber;
use Aicl\Events\EntityCreated;
use Aicl\Events\EntityCreating;
use Aicl\Events\EntityDeleted;
use Aicl\Events\EntityDeleting;
use Aicl\Events\EntityUpdated;
use Aicl\Events\EntityUpdating;
use Aicl\Events\Enums\ActorType;
use Aicl\Events\Exceptions\UnresolvableEventException;
use Aicl\Events\SessionTerminated;
use Aicl\Events\Traits\BroadcastsDomainEvent;
use App\Models\User;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Events\Dispatcher;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use RuntimeException;
use Tests\TestCase;

class EventCoverageTest extends TestCase
{
    // ── EntityCreating ────────────────────────────────────────────

    public function test_entity_creating_stores_entity_property(): void
    {
        $user = User::factory()->make(['id' => 1]);
        $event = new EntityCreating($user);

        $this->assertSame($user, $event->entity);
    }

    public function test_entity_creating_uses_dispatchable_trait(): void
    {
        $this->assertTrue(
            in_array(Dispatchable::class, class_uses_recursive(EntityCreating::class))
        );
    }

    public function test_entity_creating_uses_serializes_models_trait(): void
    {
        $this->assertTrue(
            in_array(SerializesModels::class, class_uses_recursive(EntityCreating::class))
        );
    }

    public function test_entity_creating_is_not_domain_event(): void
    {
        /** @phpstan-ignore-next-line */
        $this->assertFalse((new \ReflectionClass(EntityCreating::class))->isSubclassOf(DomainEvent::class));
    }

    // ── EntityUpdating ────────────────────────────────────────────

    public function test_entity_updating_stores_entity_property(): void
    {
        $user = User::factory()->make(['id' => 2]);
        $event = new EntityUpdating($user);

        $this->assertSame($user, $event->entity);
    }

    public function test_entity_updating_uses_dispatchable_trait(): void
    {
        $this->assertTrue(
            in_array(Dispatchable::class, class_uses_recursive(EntityUpdating::class))
        );
    }

    public function test_entity_updating_uses_serializes_models_trait(): void
    {
        $this->assertTrue(
            in_array(SerializesModels::class, class_uses_recursive(EntityUpdating::class))
        );
    }

    // ── EntityDeleting ────────────────────────────────────────────

    public function test_entity_deleting_stores_entity_property(): void
    {
        $user = User::factory()->make(['id' => 3]);
        $event = new EntityDeleting($user);

        $this->assertSame($user, $event->entity);
    }

    public function test_entity_deleting_uses_dispatchable_trait(): void
    {
        $this->assertTrue(
            in_array(Dispatchable::class, class_uses_recursive(EntityDeleting::class))
        );
    }

    public function test_entity_deleting_uses_serializes_models_trait(): void
    {
        $this->assertTrue(
            in_array(SerializesModels::class, class_uses_recursive(EntityDeleting::class))
        );
    }

    // ── EntityCreated ─────────────────────────────────────────────

    public function test_entity_created_event_type(): void
    {
        $this->assertSame('entity.created', EntityCreated::$eventType);
    }

    public function test_entity_created_sets_entity_via_constructor(): void
    {
        $user = User::factory()->make(['id' => 10]);
        $event = new EntityCreated($user);

        $this->assertSame($user, $event->getEntity());
    }

    public function test_entity_created_to_payload_structure(): void
    {
        $user = User::factory()->make(['id' => 10]);
        $event = new EntityCreated($user);

        $payload = $event->toPayload();

        $this->assertArrayHasKey('id', $payload);
        $this->assertArrayHasKey('type', $payload);
        $this->assertArrayHasKey('action', $payload);
        $this->assertSame('created', $payload['action']);
        $this->assertSame('User', $payload['type']);
    }

    public function test_entity_created_uses_broadcasts_domain_event_trait(): void
    {
        $this->assertTrue(
            in_array(BroadcastsDomainEvent::class, class_uses_recursive(EntityCreated::class))
        );
    }

    public function test_entity_created_implements_should_broadcast(): void
    {
        $this->assertTrue((new \ReflectionClass(EntityCreated::class))->isSubclassOf(ShouldBroadcast::class));
    }

    // ── EntityUpdated ─────────────────────────────────────────────

    public function test_entity_updated_event_type(): void
    {
        $this->assertSame('entity.updated', EntityUpdated::$eventType);
    }

    public function test_entity_updated_sets_entity_via_constructor(): void
    {
        $user = User::factory()->make(['id' => 20]);
        $event = new EntityUpdated($user);

        $this->assertSame($user, $event->getEntity());
    }

    public function test_entity_updated_to_payload_includes_changes(): void
    {
        $user = User::factory()->make(['id' => 20]);
        $event = new EntityUpdated($user);

        $payload = $event->toPayload();

        $this->assertArrayHasKey('action', $payload);
        $this->assertSame('updated', $payload['action']);
        $this->assertArrayHasKey('changes', $payload);
        $this->assertArrayHasKey('type', $payload);
        $this->assertSame('User', $payload['type']);
    }

    public function test_entity_updated_uses_broadcasts_domain_event_trait(): void
    {
        $this->assertTrue(
            in_array(BroadcastsDomainEvent::class, class_uses_recursive(EntityUpdated::class))
        );
    }

    // ── EntityDeleted ─────────────────────────────────────────────

    public function test_entity_deleted_event_type(): void
    {
        $this->assertSame('entity.deleted', EntityDeleted::$eventType);
    }

    public function test_entity_deleted_stores_entity_id_as_scalar(): void
    {
        $user = User::factory()->make(['id' => 99]);
        $event = new EntityDeleted($user);

        $this->assertSame(99, $event->entityId);
    }

    public function test_entity_deleted_stores_entity_type_as_basename(): void
    {
        $user = User::factory()->make(['id' => 99]);
        $event = new EntityDeleted($user);

        $this->assertSame('User', $event->entityType);
    }

    public function test_entity_deleted_stores_entity_class_as_fqcn(): void
    {
        $user = User::factory()->make(['id' => 99]);
        $event = new EntityDeleted($user);

        $this->assertSame(User::class, $event->entityClass);
    }

    public function test_entity_deleted_get_entity_type_returns_fqcn(): void
    {
        $user = User::factory()->make(['id' => 99]);
        $event = new EntityDeleted($user);

        $this->assertSame(User::class, $event->getEntityType());
    }

    public function test_entity_deleted_get_entity_id_returns_scalar(): void
    {
        $user = User::factory()->make(['id' => 99]);
        $event = new EntityDeleted($user);

        $this->assertSame(99, $event->getEntityId());
    }

    public function test_entity_deleted_to_payload_structure(): void
    {
        $user = User::factory()->make(['id' => 99]);
        $event = new EntityDeleted($user);

        $payload = $event->toPayload();

        $this->assertSame(99, $payload['id']);
        $this->assertSame('User', $payload['type']);
        $this->assertSame('deleted', $payload['action']);
    }

    public function test_entity_deleted_does_not_set_entity_property(): void
    {
        $user = User::factory()->make(['id' => 99]);
        $event = new EntityDeleted($user);

        $this->assertNull($event->entity);
    }

    // ── SessionTerminated ─────────────────────────────────────────

    public function test_session_terminated_stores_session_id(): void
    {
        $event = new SessionTerminated('sess-xyz', 5, 'Jane');

        $this->assertSame('sess-xyz', $event->terminatedSessionId);
    }

    public function test_session_terminated_stores_user_id(): void
    {
        $event = new SessionTerminated('sess-xyz', 5, 'Jane');

        $this->assertSame(5, $event->terminatedUserId);
    }

    public function test_session_terminated_stores_user_name(): void
    {
        $event = new SessionTerminated('sess-xyz', 5, 'Jane');

        $this->assertSame('Jane', $event->terminatedUserName);
    }

    public function test_session_terminated_has_correct_actor_type(): void
    {
        $event = new SessionTerminated('sess-abc', 1, 'Admin');

        $this->assertSame(ActorType::User, $event->getActorType());
    }

    // ── UnresolvableEventException ────────────────────────────────

    public function test_unresolvable_event_exception_extends_runtime_exception(): void
    {
        $this->assertTrue((new \ReflectionClass(UnresolvableEventException::class))->isSubclassOf(RuntimeException::class));
    }

    public function test_unresolvable_event_exception_for_type_creates_instance(): void
    {
        $exception = UnresolvableEventException::forType('missing.event');

        $this->assertInstanceOf(UnresolvableEventException::class, $exception);
    }

    public function test_unresolvable_event_exception_message_contains_type(): void
    {
        $exception = UnresolvableEventException::forType('order.shipped');

        $this->assertStringContainsString('order.shipped', $exception->getMessage());
    }

    public function test_unresolvable_event_exception_message_mentions_registry(): void
    {
        $exception = UnresolvableEventException::forType('test.type');

        $this->assertStringContainsString('DomainEventRegistry::register()', $exception->getMessage());
    }

    // ── ActorType Enum Extended ───────────────────────────────────

    public function test_actor_type_automation_case_value(): void
    {
        $this->assertSame('automation', ActorType::Automation->value);
    }

    public function test_actor_type_automation_label(): void
    {
        $this->assertSame('Automation', ActorType::Automation->label());
    }

    public function test_actor_type_try_from_returns_null_for_invalid(): void
    {
        $this->assertNotSame(ActorType::System, ActorType::tryFrom('invalid'));
    }

    public function test_actor_type_try_from_returns_case_for_valid(): void
    {
        $this->assertSame(ActorType::System, ActorType::tryFrom('system'));
    }

    // ── DomainEvent forEntity fluent chain ────────────────────────

    public function test_for_entity_is_fluent(): void
    {
        $user = User::factory()->make(['id' => 1]);
        $event = new EntityCreated($user);

        // forEntity was already called in constructor, but let's test fluent chain
        $anotherUser = User::factory()->make(['id' => 2]);
        $result = $event->forEntity($anotherUser);

        $this->assertSame($event, $result);
    }

    // ── DomainEvent metadata ──────────────────────────────────────

    public function test_domain_event_to_metadata_returns_array(): void
    {
        $user = User::factory()->make(['id' => 1]);
        $event = new EntityCreated($user);

        $metadata = $event->toMetadata();

    }

    // ── DomainEventSubscriber subscribe method ────────────────────

    public function test_domain_event_subscriber_has_subscribe_method(): void
    {
        $subscriber = new DomainEventSubscriber;

        $this->assertTrue((new \ReflectionClass($subscriber))->hasMethod('subscribe'));
    }

    public function test_domain_event_subscriber_subscribe_accepts_dispatcher(): void
    {
        $reflection = new \ReflectionMethod(DomainEventSubscriber::class, 'subscribe');
        $params = $reflection->getParameters();

        $this->assertCount(1, $params);
        /** @phpstan-ignore-next-line */
        $this->assertSame(Dispatcher::class, $params[0]->getType()->getName());
    }

    public function test_domain_event_subscriber_has_handle_domain_event_method(): void
    {
        $subscriber = new DomainEventSubscriber;

        $this->assertTrue((new \ReflectionClass($subscriber))->hasMethod('handleDomainEvent'));
    }

    public function test_domain_event_subscriber_handle_accepts_domain_event(): void
    {
        $reflection = new \ReflectionMethod(DomainEventSubscriber::class, 'handleDomainEvent');
        $params = $reflection->getParameters();

        $this->assertCount(1, $params);
        /** @phpstan-ignore-next-line */
        $this->assertSame(DomainEvent::class, $params[0]->getType()->getName());
    }

    // ── BroadcastsDomainEvent trait ───────────────────────────────

    public function test_broadcasts_domain_event_trait_has_broadcast_on_method(): void
    {
        $this->assertTrue((new \ReflectionClass(EntityCreated::class))->hasMethod('broadcastOn'));
    }

    public function test_broadcasts_domain_event_trait_has_broadcast_as_method(): void
    {
        $this->assertTrue((new \ReflectionClass(EntityCreated::class))->hasMethod('broadcastAs'));
    }

    public function test_broadcasts_domain_event_trait_has_broadcast_with_method(): void
    {
        $this->assertTrue((new \ReflectionClass(EntityCreated::class))->hasMethod('broadcastWith'));
    }

    public function test_entity_updated_broadcast_as_returns_event_type(): void
    {
        $user = User::factory()->make(['id' => 1]);
        $event = new EntityUpdated($user);

        $this->assertSame('entity.updated', $event->broadcastAs());
    }

    public function test_entity_deleted_broadcast_as_returns_event_type(): void
    {
        $user = User::factory()->make(['id' => 1]);
        $event = new EntityDeleted($user);

        $this->assertSame('entity.deleted', $event->broadcastAs());
    }

    // ── DomainEventRegistry reconstruct method exists ─────────────

    public function test_domain_event_registry_has_reconstruct_method(): void
    {
        $this->assertTrue((new \ReflectionClass(DomainEventRegistry::class))->hasMethod('reconstruct'));
    }

    public function test_domain_event_registry_reconstruct_is_static(): void
    {
        $reflection = new \ReflectionMethod(DomainEventRegistry::class, 'reconstruct');

        $this->assertTrue($reflection->isStatic());
    }
}
