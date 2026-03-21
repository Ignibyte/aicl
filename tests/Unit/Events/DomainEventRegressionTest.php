<?php

declare(strict_types=1);

namespace Aicl\Tests\Unit\Events;

use Aicl\Events\DomainEvent;
use Aicl\Events\Enums\ActorType;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Regression tests for DomainEvent PHPStan changes.
 *
 * Tests the (int) type cast added to auth()->id() in resolveActor().
 * Under strict_types, auth()->id() can return int|string|null.
 * The (int) cast ensures actorId is always an integer when authenticated.
 * The actorType and actorId properties are protected, so we test via
 * the toSerializedArray() method or reflection.
 */
class DomainEventRegressionTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Test resolveActor casts auth()->id() to int when user is authenticated.
     *
     * PHPStan change: Added (int) cast to auth()->id() which can return
     * string|int|null. Under strict_types, assigning a string id to an
     * int-typed property would throw a TypeError.
     */
    public function test_resolve_actor_casts_auth_id_to_int_when_authenticated(): void
    {
        // Arrange: authenticate a user
        $user = User::factory()->create();
        $this->actingAs($user);

        // Act: create a concrete domain event that calls resolveActor()
        $event = new class extends DomainEvent
        {
            public static string $eventType = 'test.regression';

            /** @return array<string, mixed> */
            public function toPayload(): array
            {
                return [];
            }

            /** Expose protected actorId for testing. */
            public function getActorId(): ?int
            {
                return $this->actorId;
            }

            /** Expose protected actorType for testing. */
            public function getActorType(): ActorType
            {
                return $this->actorType;
            }
        };

        // Assert: actorId should be int type, not string
        $this->assertSame(ActorType::User, $event->getActorType());
        $this->assertIsInt($event->getActorId());
        $this->assertSame((int) $user->id, $event->getActorId());
    }

    /**
     * Test resolveActor sets system actor type when running in console.
     *
     * Verifies behavior is preserved after strict_types declaration.
     */
    public function test_resolve_actor_sets_system_when_unauthenticated(): void
    {
        // Arrange: ensure no user is authenticated
        auth()->guard()->logout();

        // Act
        $event = new class extends DomainEvent
        {
            public static string $eventType = 'test.regression.system';

            /** @return array<string, mixed> */
            public function toPayload(): array
            {
                return [];
            }

            /** Expose protected actorType for testing. */
            public function getActorType(): ActorType
            {
                return $this->actorType;
            }

            /** Expose protected actorId for testing. */
            public function getActorId(): ?int
            {
                return $this->actorId;
            }
        };

        // Assert: should be system actor with null id
        $this->assertSame(ActorType::System, $event->getActorType());
        $this->assertNull($event->getActorId());
    }
}
