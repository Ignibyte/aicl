<?php

namespace Aicl\Tests\Unit\Broadcasting;

use Aicl\Broadcasting\BaseBroadcastEvent;
use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class BaseBroadcastEventTest extends TestCase
{
    use RefreshDatabase;

    // ── eventId ─────────────────────────────────────────────────

    public function test_event_id_is_uuid(): void
    {
        $event = new TestBroadcastEvent;

        $this->assertTrue(Str::isUuid($event->eventId));
    }

    public function test_unique_event_ids_per_instance(): void
    {
        $event1 = new TestBroadcastEvent;
        $event2 = new TestBroadcastEvent;

        $this->assertNotSame($event1->eventId, $event2->eventId);
    }

    // ── eventType ───────────────────────────────────────────────

    public function test_event_type_is_set_from_static_method(): void
    {
        $event = new TestBroadcastEvent;

        $this->assertSame('test.event', $event->eventType);
    }

    // ── occurredAt ──────────────────────────────────────────────

    public function test_occurred_at_is_iso8601(): void
    {
        $event = new TestBroadcastEvent;

        $parsed = \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $event->occurredAt);

        $this->assertNotFalse($parsed, 'occurredAt should be a valid ISO8601 string');
    }

    // ── broadcastOn() ───────────────────────────────────────────

    public function test_broadcast_on_includes_dashboard_channel(): void
    {
        $event = new TestBroadcastEvent;

        $channels = $event->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
        $this->assertSame('private-dashboard', $channels[0]->name);
    }

    public function test_broadcast_on_without_entity_has_only_dashboard(): void
    {
        $event = new TestBroadcastEvent;

        $channels = $event->broadcastOn();

        $this->assertCount(1, $channels);
    }

    public function test_broadcast_on_with_entity_adds_entity_channel(): void
    {
        $user = User::factory()->create();
        $event = new TestBroadcastEventWithEntity($user);

        $channels = $event->broadcastOn();

        $this->assertCount(2, $channels);
        $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
        $this->assertInstanceOf(PrivateChannel::class, $channels[1]);
        $this->assertSame('private-dashboard', $channels[0]->name);
        $this->assertSame("private-users.{$user->getKey()}", $channels[1]->name);
    }

    // ── broadcastAs() ───────────────────────────────────────────

    public function test_broadcast_as_returns_event_type(): void
    {
        $event = new TestBroadcastEvent;

        $this->assertSame('test.event', $event->broadcastAs());
    }

    // ── broadcastWith() ─────────────────────────────────────────

    public function test_broadcast_with_includes_envelope_fields(): void
    {
        $event = new TestBroadcastEvent;

        $data = $event->broadcastWith();

        $this->assertArrayHasKey('eventId', $data);
        $this->assertArrayHasKey('eventType', $data);
        $this->assertArrayHasKey('occurredAt', $data);
        $this->assertSame($event->eventId, $data['eventId']);
        $this->assertSame('test.event', $data['eventType']);
        $this->assertSame($event->occurredAt, $data['occurredAt']);
    }

    public function test_broadcast_with_merges_payload(): void
    {
        $event = new TestBroadcastEventWithPayload(['foo' => 'bar', 'count' => 42]);

        $data = $event->broadcastWith();

        $this->assertSame('bar', $data['foo']);
        $this->assertSame(42, $data['count']);
        $this->assertArrayHasKey('eventId', $data);
        $this->assertArrayHasKey('eventType', $data);
        $this->assertArrayHasKey('occurredAt', $data);
    }

    // ── toPayload() / getEntity() defaults ──────────────────────

    public function test_default_payload_is_empty(): void
    {
        $event = new TestBroadcastEvent;

        $this->assertSame([], $event->toPayload());
    }

    public function test_default_entity_is_null(): void
    {
        $event = new TestBroadcastEvent;

        $this->assertNull($event->getEntity());
    }
}

// ── Test Stubs ────────────────────────────────────────────────────

class TestBroadcastEvent extends BaseBroadcastEvent
{
    public static function eventType(): string
    {
        return 'test.event';
    }
}

class TestBroadcastEventWithPayload extends BaseBroadcastEvent
{
    /** @phpstan-ignore-next-line */
    public function __construct(public readonly array $payload = [])
    {
        parent::__construct();
    }

    public static function eventType(): string
    {
        return 'test.with_payload';
    }

    public function toPayload(): array
    {
        return $this->payload;
    }
}

class TestBroadcastEventWithEntity extends BaseBroadcastEvent
{
    public function __construct(private Model $entity)
    {
        parent::__construct();
    }

    public static function eventType(): string
    {
        return 'test.with_entity';
    }

    public function getEntity(): ?Model
    {
        return $this->entity;
    }
}
