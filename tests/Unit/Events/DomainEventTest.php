<?php

namespace Aicl\Tests\Unit\Events;

use Aicl\Events\DomainEvent;
use Aicl\Events\DomainEventRegistry;
use Aicl\Events\Enums\ActorType;
use Aicl\Events\Exceptions\UnresolvableEventException;
use PHPUnit\Framework\TestCase;

class DomainEventTest extends TestCase
{
    protected function tearDown(): void
    {
        DomainEventRegistry::flush();
        parent::tearDown();
    }

    // ── ActorType Enum ────────────────────────────────────────────

    public function test_actor_type_has_four_cases(): void
    {
        $cases = ActorType::cases();

        $this->assertCount(4, $cases);
        $this->assertSame('user', ActorType::User->value);
        $this->assertSame('system', ActorType::System->value);
        $this->assertSame('agent', ActorType::Agent->value);
        $this->assertSame('automation', ActorType::Automation->value);
    }

    public function test_actor_type_labels(): void
    {
        $this->assertSame('User', ActorType::User->label());
        $this->assertSame('System', ActorType::System->label());
        $this->assertSame('AI Agent', ActorType::Agent->label());
        $this->assertSame('Automation', ActorType::Automation->label());
    }

    public function test_actor_type_can_be_created_from_string(): void
    {
        $this->assertSame(ActorType::User, ActorType::from('user'));
        $this->assertSame(ActorType::Agent, ActorType::from('agent'));
    }

    // ── DomainEventRegistry ──────────────────────────────────────

    public function test_registry_starts_empty(): void
    {
        DomainEventRegistry::flush();

        $this->assertSame([], DomainEventRegistry::all());
    }

    public function test_registry_register_and_has(): void
    {
        DomainEventRegistry::register('test.created', StubDomainEvent::class);

        $this->assertTrue(DomainEventRegistry::has('test.created'));
        $this->assertFalse(DomainEventRegistry::has('test.nonexistent'));
    }

    public function test_registry_resolve_returns_class(): void
    {
        DomainEventRegistry::register('test.created', StubDomainEvent::class);

        $this->assertSame(StubDomainEvent::class, DomainEventRegistry::resolve('test.created'));
    }

    public function test_registry_resolve_throws_for_unregistered(): void
    {
        $this->expectException(UnresolvableEventException::class);
        $this->expectExceptionMessage("Cannot resolve event class for type 'nonexistent.type'");

        DomainEventRegistry::resolve('nonexistent.type');
    }

    public function test_registry_all_returns_all_registrations(): void
    {
        DomainEventRegistry::register('test.created', StubDomainEvent::class);
        DomainEventRegistry::register('test.updated', StubDomainEvent::class);

        $all = DomainEventRegistry::all();

        $this->assertCount(2, $all);
        $this->assertArrayHasKey('test.created', $all);
        $this->assertArrayHasKey('test.updated', $all);
    }

    public function test_registry_flush_clears_all(): void
    {
        DomainEventRegistry::register('test.created', StubDomainEvent::class);
        DomainEventRegistry::flush();

        $this->assertSame([], DomainEventRegistry::all());
        $this->assertFalse(DomainEventRegistry::has('test.created'));
    }

    // ── DomainEvent Construction ─────────────────────────────────

    public function test_event_has_uuid_event_id(): void
    {
        $event = new StubDomainEvent(ActorType::System);

        $this->assertNotEmpty($event->eventId);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $event->eventId
        );
    }

    public function test_event_has_occurred_at_timestamp(): void
    {
        $event = new StubDomainEvent(ActorType::System);

        $this->assertNotNull($event->occurredAt);
        $this->assertInstanceOf(\Carbon\Carbon::class, $event->occurredAt);
    }

    public function test_event_accepts_explicit_actor_type(): void
    {
        $event = new StubDomainEvent(ActorType::Agent, 42);

        $this->assertSame(ActorType::Agent, $event->getActorType());
        $this->assertSame(42, $event->getActorId());
    }

    public function test_event_accepts_explicit_actor_with_null_id(): void
    {
        $event = new StubDomainEvent(ActorType::Automation);

        $this->assertSame(ActorType::Automation, $event->getActorType());
        $this->assertNull($event->getActorId());
    }

    public function test_event_returns_correct_event_type(): void
    {
        $event = new StubDomainEvent(ActorType::System);

        $this->assertSame('test.stub_event', $event->getEventType());
    }

    public function test_event_default_payload_is_empty(): void
    {
        $event = new StubDomainEvent(ActorType::System);

        $this->assertSame([], $event->toPayload());
    }

    public function test_event_custom_payload(): void
    {
        $event = new StubDomainEventWithPayload(ActorType::System);

        $this->assertSame(['key' => 'value', 'count' => 42], $event->toPayload());
    }

    public function test_event_entity_is_null_by_default(): void
    {
        $event = new StubDomainEvent(ActorType::System);

        $this->assertNull($event->getEntity());
        $this->assertNull($event->getEntityType());
        $this->assertNull($event->getEntityId());
    }

    // ── Replay Flag ──────────────────────────────────────────────

    public function test_event_is_not_replay_by_default(): void
    {
        $event = new StubDomainEvent(ActorType::System);

        $this->assertFalse($event->isReplay());
    }

    public function test_event_can_be_marked_as_replay(): void
    {
        $event = new StubDomainEvent(ActorType::System);
        $result = $event->markAsReplay();

        $this->assertTrue($event->isReplay());
        $this->assertSame($event, $result);
    }

    // ── Static Registration ──────────────────────────────────────

    public function test_event_static_register(): void
    {
        StubDomainEvent::register();

        $this->assertTrue(DomainEventRegistry::has('test.stub_event'));
        $this->assertSame(StubDomainEvent::class, DomainEventRegistry::resolve('test.stub_event'));
    }

    // ── Each event gets unique ID ────────────────────────────────

    public function test_each_event_instance_gets_unique_id(): void
    {
        $event1 = new StubDomainEvent(ActorType::System);
        $event2 = new StubDomainEvent(ActorType::System);

        $this->assertNotSame($event1->eventId, $event2->eventId);
    }
}

// ── Stubs ─────────────────────────────────────────────────────────

class StubDomainEvent extends DomainEvent
{
    public static string $eventType = 'test.stub_event';

    public function __construct(?ActorType $actorType = null, ?int $actorId = null)
    {
        parent::__construct($actorType, $actorId);
    }
}

class StubDomainEventWithPayload extends DomainEvent
{
    public static string $eventType = 'test.stub_payload';

    public function __construct(?ActorType $actorType = null, ?int $actorId = null)
    {
        parent::__construct($actorType, $actorId);
    }

    public function toPayload(): array
    {
        return ['key' => 'value', 'count' => 42];
    }
}
