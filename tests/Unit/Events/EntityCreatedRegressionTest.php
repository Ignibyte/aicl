<?php

declare(strict_types=1);

namespace Aicl\Tests\Unit\Events;

use Aicl\Events\EntityCreated;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Regression tests for EntityCreated PHPStan changes.
 *
 * Tests the null guard added to toPayload() which returns a minimal
 * payload when $this->entity is null. Previously, accessing getKey()
 * on null would throw a fatal error under strict_types.
 */
class EntityCreatedRegressionTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Test toPayload returns full payload when entity is present.
     *
     * Happy path: entity is set via forEntity() in the constructor.
     */
    public function test_to_payload_returns_full_payload_with_entity(): void
    {
        // Arrange: create a real user model as the entity
        $user = User::factory()->create(['name' => 'Test User']);

        // Act: create the event with a model
        $event = new EntityCreated($user);
        $payload = $event->toPayload();

        // Assert: payload should include id, type, and action
        $this->assertArrayHasKey('id', $payload);
        $this->assertSame($user->getKey(), $payload['id']);
        $this->assertSame('User', $payload['type']);
        $this->assertSame('created', $payload['action']);
    }

    /**
     * Test toPayload returns minimal payload when entity is null.
     *
     * PHPStan change: Added null guard `if (! $this->entity)` which
     * returns `['action' => 'created']` instead of crashing.
     * This can happen if the entity is cleared after construction.
     */
    public function test_to_payload_returns_minimal_payload_when_entity_null(): void
    {
        // Arrange: create event, then clear entity via reflection
        $user = User::factory()->create();
        $event = new EntityCreated($user);

        // Null out the entity to simulate the PHPStan-guarded path
        $reflection = new \ReflectionProperty($event, 'entity');
        $reflection->setAccessible(true);
        $reflection->setValue($event, null);

        // Act
        $payload = $event->toPayload();

        // Assert: should return minimal payload with just the action
        $this->assertSame(['action' => 'created'], $payload);
    }

    /**
     * Test EntityCreated sets correct event type.
     *
     * Verifies the static $eventType property is correct after
     * declare(strict_types=1) was added.
     */
    public function test_event_type_is_entity_created(): void
    {
        // Assert: event type constant should be entity.created
        $this->assertSame('entity.created', EntityCreated::$eventType);
    }
}
