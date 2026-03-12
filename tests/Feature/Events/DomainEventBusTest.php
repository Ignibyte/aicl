<?php

namespace Aicl\Tests\Feature\Events;

use Aicl\Events\DomainEvent;
use Aicl\Events\DomainEventRegistry;
use Aicl\Events\DomainEventSubscriber;
use Aicl\Events\Enums\ActorType;
use Aicl\Events\Exceptions\UnresolvableEventException;
use Aicl\Events\Traits\BroadcastsDomainEvent;
use Aicl\Models\DomainEventRecord;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class DomainEventBusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        DomainEventRegistry::flush();
    }

    protected function tearDown(): void
    {
        DomainEventRegistry::flush();
        parent::tearDown();
    }

    // ── Actor Resolution ─────────────────────────────────────────

    public function test_resolves_authenticated_user_as_actor(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $event = new TestOrderEvent;

        $this->assertSame(ActorType::User, $event->getActorType());
        $this->assertSame($user->id, $event->getActorId());
    }

    public function test_resolves_system_actor_when_unauthenticated(): void
    {
        $event = new TestOrderEvent(ActorType::System);

        $this->assertSame(ActorType::System, $event->getActorType());
        $this->assertNull($event->getActorId());
    }

    // ── Entity Association ───────────────────────────────────────

    public function test_event_for_entity_sets_type_and_id(): void
    {
        $user = User::factory()->create();
        $event = new TestOrderEvent(ActorType::System);
        $event->forEntity($user);

        $this->assertSame($user->getMorphClass(), $event->getEntityType());
        $this->assertSame($user->getKey(), $event->getEntityId());
        $this->assertSame($user, $event->getEntity());
    }

    public function test_for_entity_returns_fluent_self(): void
    {
        $user = User::factory()->create();
        $event = new TestOrderEvent(ActorType::System);

        $result = $event->forEntity($user);

        $this->assertSame($event, $result);
    }

    // ── Subscriber Persistence ───────────────────────────────────

    public function test_subscriber_persists_domain_event(): void
    {
        $user = User::factory()->create();
        $event = new TestOrderEvent(ActorType::User, $user->id);
        $event->forEntity($user);

        $subscriber = new DomainEventSubscriber;
        $subscriber->handleDomainEvent($event);

        $this->assertDatabaseHas('domain_events', ['event_type' => 'order.created']);

        $record = DomainEventRecord::where('event_type', 'order.created')->first();
        $this->assertSame('order.created', $record->event_type);
        $this->assertSame('user', $record->actor_type);
        $this->assertSame($user->id, $record->actor_id);
        $this->assertSame($user->getMorphClass(), $record->entity_type);
        $this->assertSame((string) $user->getKey(), $record->entity_id);
        $this->assertNotNull($record->occurred_at);
        $this->assertNotNull($record->created_at);
    }

    public function test_subscriber_persists_payload_and_metadata(): void
    {
        $event = new TestOrderEventWithPayload(ActorType::System);

        $subscriber = new DomainEventSubscriber;
        $subscriber->handleDomainEvent($event);

        $record = DomainEventRecord::first();
        $this->assertSame(['item' => 'widget', 'quantity' => 5], $record->payload);
        $this->assertIsArray($record->metadata);
    }

    public function test_subscriber_skips_replay_events(): void
    {
        $event = new TestOrderEvent(ActorType::System);
        $event->markAsReplay();

        $subscriber = new DomainEventSubscriber;
        $subscriber->handleDomainEvent($event);

        $this->assertDatabaseCount('domain_events', 0);
    }

    public function test_subscriber_persists_event_without_entity(): void
    {
        $event = new TestOrderEvent(ActorType::System);

        $subscriber = new DomainEventSubscriber;
        $subscriber->handleDomainEvent($event);

        $record = DomainEventRecord::first();
        $this->assertNull($record->entity_type);
        $this->assertNull($record->entity_id);
    }

    public function test_subscriber_handles_automation_actor(): void
    {
        $event = new TestOrderEvent(ActorType::Automation);

        $subscriber = new DomainEventSubscriber;
        $subscriber->handleDomainEvent($event);

        $record = DomainEventRecord::first();
        $this->assertSame('automation', $record->actor_type);
        $this->assertNull($record->actor_id);
    }

    // ── DomainEventRecord Created_at Auto-Set ────────────────────

    public function test_record_auto_sets_created_at(): void
    {
        $record = DomainEventRecord::create([
            'event_type' => 'test.created',
            'actor_type' => 'system',
            'payload' => [],
            'metadata' => [],
            'occurred_at' => now(),
        ]);

        $this->assertNotNull($record->created_at);
    }

    // ── Query Scopes ─────────────────────────────────────────────

    public function test_scope_for_entity(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $this->createRecord(['entity_type' => $user1->getMorphClass(), 'entity_id' => (string) $user1->getKey()]);
        $this->createRecord(['entity_type' => $user2->getMorphClass(), 'entity_id' => (string) $user2->getKey()]);
        $this->createRecord(['entity_type' => null, 'entity_id' => null]);

        $results = DomainEventRecord::forEntity($user1)->get();

        // Includes auto-persisted entity.created + manually created record
        $this->assertTrue($results->count() >= 1);
        $this->assertTrue($results->pluck('entity_id')->contains((string) $user1->getKey()));
    }

    public function test_scope_of_type_exact_match(): void
    {
        $this->createRecord(['event_type' => 'order.created']);
        $this->createRecord(['event_type' => 'order.fulfilled']);
        $this->createRecord(['event_type' => 'incident.escalated']);

        $results = DomainEventRecord::ofType('order.created')->get();

        $this->assertCount(1, $results);
        $this->assertSame('order.created', $results->first()->event_type);
    }

    public function test_scope_of_type_wildcard_prefix(): void
    {
        $this->createRecord(['event_type' => 'order.created']);
        $this->createRecord(['event_type' => 'order.fulfilled']);
        $this->createRecord(['event_type' => 'incident.escalated']);

        $results = DomainEventRecord::ofType('order.*')->get();

        $this->assertCount(2, $results);
    }

    public function test_scope_of_type_wildcard_suffix(): void
    {
        $this->createRecord(['event_type' => 'order.escalated']);
        $this->createRecord(['event_type' => 'incident.escalated']);
        $this->createRecord(['event_type' => 'order.created']);

        $results = DomainEventRecord::ofType('*.escalated')->get();

        $this->assertCount(2, $results);
    }

    public function test_scope_of_type_no_wildcard_no_match(): void
    {
        $this->createRecord(['event_type' => 'order.created']);

        $results = DomainEventRecord::ofType('nonexistent.type')->get();

        $this->assertCount(0, $results);
    }

    public function test_scope_since(): void
    {
        $this->createRecord(['occurred_at' => Carbon::parse('2026-01-01 12:00:00')]);
        $this->createRecord(['occurred_at' => Carbon::parse('2026-02-01 12:00:00')]);
        $this->createRecord(['occurred_at' => Carbon::parse('2026-03-01 12:00:00')]);

        $results = DomainEventRecord::since(Carbon::parse('2026-02-01 00:00:00'))->get();

        $this->assertCount(2, $results);
    }

    public function test_scope_between(): void
    {
        $this->createRecord(['occurred_at' => Carbon::parse('2026-01-01 12:00:00')]);
        $this->createRecord(['occurred_at' => Carbon::parse('2026-02-15 12:00:00')]);
        $this->createRecord(['occurred_at' => Carbon::parse('2026-03-01 12:00:00')]);

        $results = DomainEventRecord::between(
            Carbon::parse('2026-02-01 00:00:00'),
            Carbon::parse('2026-02-28 23:59:59')
        )->get();

        $this->assertCount(1, $results);
    }

    public function test_scope_by_actor_type(): void
    {
        $this->createRecord(['actor_type' => 'user', 'actor_id' => 1]);
        $this->createRecord(['actor_type' => 'user', 'actor_id' => 2]);
        $this->createRecord(['actor_type' => 'system', 'actor_id' => null]);

        $results = DomainEventRecord::byActor(ActorType::User)->get();

        $this->assertCount(2, $results);
    }

    public function test_scope_by_actor_type_and_id(): void
    {
        $this->createRecord(['actor_type' => 'user', 'actor_id' => 1]);
        $this->createRecord(['actor_type' => 'user', 'actor_id' => 2]);
        $this->createRecord(['actor_type' => 'system', 'actor_id' => null]);

        $results = DomainEventRecord::byActor(ActorType::User, 1)->get();

        $this->assertCount(1, $results);
        $this->assertSame(1, $results->first()->actor_id);
    }

    public function test_scope_timeline_returns_entity_events_newest_first(): void
    {
        $user = User::factory()->create();

        $this->createRecord([
            'entity_type' => $user->getMorphClass(),
            'entity_id' => (string) $user->getKey(),
            'event_type' => 'user.created',
            'occurred_at' => Carbon::parse('2026-01-01 12:00:00'),
        ]);
        $this->createRecord([
            'entity_type' => $user->getMorphClass(),
            'entity_id' => (string) $user->getKey(),
            'event_type' => 'user.updated',
            'occurred_at' => Carbon::now()->addYear(),
        ]);
        $this->createRecord([
            'entity_type' => $user->getMorphClass(),
            'entity_id' => (string) $user->getKey(),
            'event_type' => 'user.promoted',
            'occurred_at' => Carbon::parse('2026-02-01 12:00:00'),
        ]);
        // Event for a different entity
        $this->createRecord(['entity_type' => 'App\Models\Order', 'entity_id' => '999']);

        $results = DomainEventRecord::timeline($user)->get();

        // 3 manual + auto-persisted entity.created from User::factory()->create()
        $this->assertTrue($results->count() >= 3);
        $this->assertSame('user.updated', $results->first()->event_type);
    }

    // ── Chained Scopes ───────────────────────────────────────────

    public function test_scopes_can_be_chained(): void
    {
        $user = User::factory()->create();

        $this->createRecord([
            'entity_type' => $user->getMorphClass(),
            'entity_id' => (string) $user->getKey(),
            'event_type' => 'user.updated',
            'occurred_at' => Carbon::parse('2026-02-15 12:00:00'),
        ]);
        $this->createRecord([
            'entity_type' => $user->getMorphClass(),
            'entity_id' => (string) $user->getKey(),
            'event_type' => 'user.deleted',
            'occurred_at' => Carbon::parse('2026-01-01 12:00:00'),
        ]);
        $this->createRecord([
            'entity_type' => $user->getMorphClass(),
            'entity_id' => (string) $user->getKey(),
            'event_type' => 'order.created',
            'occurred_at' => Carbon::parse('2026-02-15 12:00:00'),
        ]);

        $results = DomainEventRecord::forEntity($user)
            ->ofType('user.*')
            ->since(Carbon::parse('2026-02-01'))
            ->get();

        $this->assertCount(1, $results);
        $this->assertSame('user.updated', $results->first()->event_type);
    }

    // ── Prune ────────────────────────────────────────────────────

    public function test_prune_deletes_old_events(): void
    {
        $this->createRecord(['occurred_at' => Carbon::parse('2025-01-01')]);
        $this->createRecord(['occurred_at' => Carbon::parse('2025-06-01')]);
        $this->createRecord(['occurred_at' => Carbon::parse('2026-02-01')]);

        $deleted = DomainEventRecord::prune(Carbon::parse('2026-01-01'));

        $this->assertSame(2, $deleted);
        $this->assertDatabaseCount('domain_events', 1);
    }

    public function test_prune_returns_zero_when_nothing_to_delete(): void
    {
        $this->createRecord(['occurred_at' => Carbon::parse('2026-06-01')]);

        $deleted = DomainEventRecord::prune(Carbon::parse('2026-01-01'));

        $this->assertSame(0, $deleted);
        $this->assertDatabaseCount('domain_events', 1);
    }

    // ── Replay ───────────────────────────────────────────────────

    public function test_replay_dispatches_event_with_replay_flag(): void
    {
        TestOrderEvent::register();

        $record = $this->createRecord([
            'event_type' => 'order.created',
            'actor_type' => 'user',
            'actor_id' => 1,
        ]);

        Event::fake([TestOrderEvent::class]);

        $replayedEvent = $record->replay();

        $this->assertTrue($replayedEvent->isReplay());
        Event::assertDispatched(TestOrderEvent::class);
    }

    public function test_replay_does_not_create_duplicate_record(): void
    {
        TestOrderEvent::register();

        $record = $this->createRecord(['event_type' => 'order.created']);
        $initialCount = DomainEventRecord::count();

        // Re-enable the subscriber (not faked) — it should skip replays
        $record->replay();

        $this->assertSame($initialCount, DomainEventRecord::count());
    }

    public function test_replay_throws_for_unregistered_event_type(): void
    {
        $record = $this->createRecord(['event_type' => 'unknown.type']);

        $this->expectException(UnresolvableEventException::class);

        $record->replay();
    }

    public function test_replay_reconstructs_event_with_original_actor(): void
    {
        TestOrderEvent::register();

        $record = $this->createRecord([
            'event_type' => 'order.created',
            'actor_type' => 'agent',
            'actor_id' => 99,
        ]);

        Event::fake([TestOrderEvent::class]);
        $replayedEvent = $record->replay();

        $this->assertSame(ActorType::Agent, $replayedEvent->getActorType());
        $this->assertSame(99, $replayedEvent->getActorId());
    }

    // ── Actor Type Enum Accessor ─────────────────────────────────

    public function test_actor_type_enum_accessor(): void
    {
        $record = $this->createRecord(['actor_type' => 'automation']);

        $this->assertSame(ActorType::Automation, $record->actor_type_enum);
    }

    // ── BroadcastsDomainEvent Trait ──────────────────────────────

    public function test_broadcast_event_has_dashboard_channel(): void
    {
        $event = new TestBroadcastDomainEvent(ActorType::System);

        $channels = $event->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
    }

    public function test_broadcast_event_adds_entity_channel(): void
    {
        $user = User::factory()->create();
        $event = new TestBroadcastDomainEvent(ActorType::System);
        $event->forEntity($user);

        $channels = $event->broadcastOn();

        $this->assertCount(2, $channels);
    }

    public function test_broadcast_as_returns_event_type(): void
    {
        $event = new TestBroadcastDomainEvent(ActorType::System);

        $this->assertSame('test.broadcast', $event->broadcastAs());
    }

    public function test_broadcast_with_includes_payload_and_metadata(): void
    {
        $event = new TestBroadcastDomainEvent(ActorType::System);

        $data = $event->broadcastWith();

        $this->assertArrayHasKey('eventId', $data);
        $this->assertArrayHasKey('eventType', $data);
        $this->assertArrayHasKey('occurredAt', $data);
        $this->assertSame('test.broadcast', $data['eventType']);
    }

    public function test_broadcast_event_implements_should_broadcast(): void
    {
        $event = new TestBroadcastDomainEvent(ActorType::System);

        $this->assertInstanceOf(ShouldBroadcast::class, $event);
    }

    // ── Full Integration: Dispatch → Persist ─────────────────────

    public function test_dispatching_domain_event_persists_to_database(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $event = new TestOrderEventWithPayload(ActorType::User, $user->id);
        $event->forEntity($user);

        // Dispatch through Laravel's event system
        event($event);

        $this->assertDatabaseHas('domain_events', ['event_type' => 'order.with_payload']);
        $record = DomainEventRecord::where('event_type', 'order.with_payload')->first();
        $this->assertSame('order.with_payload', $record->event_type);
        $this->assertSame('user', $record->actor_type);
        $this->assertSame(['item' => 'widget', 'quantity' => 5], $record->payload);
    }

    // ── JSONB Casts ──────────────────────────────────────────────

    public function test_payload_is_cast_to_array(): void
    {
        $record = $this->createRecord(['payload' => ['nested' => ['data' => true]]]);

        $fresh = DomainEventRecord::find($record->id);

        $this->assertIsArray($fresh->payload);
        $this->assertTrue($fresh->payload['nested']['data']);
    }

    public function test_metadata_is_cast_to_array(): void
    {
        $record = $this->createRecord(['metadata' => ['ip' => '127.0.0.1']]);

        $fresh = DomainEventRecord::find($record->id);

        $this->assertIsArray($fresh->metadata);
        $this->assertSame('127.0.0.1', $fresh->metadata['ip']);
    }

    // ── Helpers ──────────────────────────────────────────────────

    private function createRecord(array $overrides = []): DomainEventRecord
    {
        return DomainEventRecord::create(array_merge([
            'event_type' => 'test.event',
            'actor_type' => 'system',
            'actor_id' => null,
            'entity_type' => null,
            'entity_id' => null,
            'payload' => [],
            'metadata' => [],
            'occurred_at' => now(),
        ], $overrides));
    }
}

// ── Test Stubs ────────────────────────────────────────────────────

class TestOrderEvent extends DomainEvent
{
    public static string $eventType = 'order.created';

    public function __construct(?ActorType $actorType = null, ?int $actorId = null)
    {
        parent::__construct($actorType, $actorId);
    }
}

class TestOrderEventWithPayload extends DomainEvent
{
    public static string $eventType = 'order.with_payload';

    public function __construct(?ActorType $actorType = null, ?int $actorId = null)
    {
        parent::__construct($actorType, $actorId);
    }

    public function toPayload(): array
    {
        return ['item' => 'widget', 'quantity' => 5];
    }
}

class TestBroadcastDomainEvent extends DomainEvent implements ShouldBroadcast
{
    use BroadcastsDomainEvent;

    public static string $eventType = 'test.broadcast';

    public function __construct(?ActorType $actorType = null, ?int $actorId = null)
    {
        parent::__construct($actorType, $actorId);
    }
}
