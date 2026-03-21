<?php

declare(strict_types=1);

namespace Aicl\Tests\Unit\Events;

use Aicl\Events\EntityUpdated;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Regression tests for EntityUpdated PHPStan changes.
 *
 * Tests the null guard added to toPayload() which returns a minimal
 * payload when $this->entity is null. Same pattern as EntityCreated.
 */
class EntityUpdatedRegressionTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Test toPayload returns full payload when entity is present.
     *
     * Happy path: entity has all fields populated.
     */
    public function test_to_payload_returns_full_payload_with_entity(): void
    {
        // Arrange
        $user = User::factory()->create();

        // Act
        $event = new EntityUpdated($user);
        $payload = $event->toPayload();

        // Assert: should include id, type, action, and changes
        $this->assertArrayHasKey('id', $payload);
        $this->assertSame($user->getKey(), $payload['id']);
        $this->assertSame('User', $payload['type']);
        $this->assertSame('updated', $payload['action']);
    }

    /**
     * Test toPayload returns minimal payload when entity is null.
     *
     * PHPStan change: Null guard returns ['action' => 'updated']
     * instead of crashing when entity is null.
     */
    public function test_to_payload_returns_minimal_payload_when_entity_null(): void
    {
        // Arrange: create event, then clear entity
        $user = User::factory()->create();
        $event = new EntityUpdated($user);

        // Clear entity via reflection
        $reflection = new \ReflectionProperty($event, 'entity');
        $reflection->setAccessible(true);
        $reflection->setValue($event, null);

        // Act
        $payload = $event->toPayload();

        // Assert: should return minimal payload
        $this->assertSame(['action' => 'updated'], $payload);
    }

    /**
     * Test event type property after strict_types addition.
     */
    public function test_event_type_is_entity_updated(): void
    {
        $this->assertSame('entity.updated', EntityUpdated::$eventType);
    }
}
