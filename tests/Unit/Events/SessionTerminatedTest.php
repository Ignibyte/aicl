<?php

namespace Aicl\Tests\Unit\Events;

use Aicl\Events\DomainEvent;
use Aicl\Events\DomainEventRegistry;
use Aicl\Events\SessionTerminated;
use Tests\TestCase;

class SessionTerminatedTest extends TestCase
{
    public function test_extends_domain_event(): void
    {
        $this->assertTrue(is_subclass_of(SessionTerminated::class, DomainEvent::class));
    }

    public function test_event_type_is_session_terminated(): void
    {
        $this->assertSame('session.terminated', SessionTerminated::$eventType);
    }

    public function test_to_payload_returns_expected_structure(): void
    {
        $event = new SessionTerminated('sess-abc123', 42, 'John Doe');

        $payload = $event->toPayload();

        $this->assertSame('session_terminated', $payload['action']);
        $this->assertSame('sess-abc123', $payload['terminated_session_id']);
        $this->assertSame(42, $payload['terminated_user_id']);
        $this->assertSame('John Doe', $payload['terminated_user_name']);
    }

    public function test_event_is_registered_in_domain_event_registry(): void
    {
        $this->assertTrue(DomainEventRegistry::has('session.terminated'));
        $this->assertSame(SessionTerminated::class, DomainEventRegistry::resolve('session.terminated'));
    }
}
