<?php

declare(strict_types=1);

namespace Aicl\Tests\Unit\Events;

use Aicl\Events\Enums\ActorType;
use Aicl\Events\SessionTerminated;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Regression tests for SessionTerminated PHPStan changes.
 *
 * Tests the (int) type cast added to auth()->id() in the constructor.
 * Under strict_types, auth()->id() can return string|int|null, and
 * the parent constructor expects int|null for actorId.
 */
class SessionTerminatedRegressionTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Test constructor casts auth()->id() to int.
     *
     * PHPStan change: Added (int) cast to auth()->id() which is passed
     * to parent::__construct(ActorType::User, (int) auth()->id()).
     * We verify via the serialized array output since actorId is protected.
     */
    public function test_constructor_casts_auth_id_to_int(): void
    {
        // Arrange: authenticate a user
        $user = User::factory()->create();
        $this->actingAs($user);

        // Act: create the session terminated event
        $event = new SessionTerminated(
            terminatedSessionId: 'abc-123',
            terminatedUserId: 42,
            terminatedUserName: 'Jane Doe',
        );

        // Assert: the event should be created without TypeError
        // (would throw if (int) cast was removed with strict_types)
        $this->assertSame(ActorType::User, $event->getActorType());
        $this->assertIsInt($event->getActorId());
        $this->assertSame((int) $user->id, $event->getActorId());
    }

    /**
     * Test event stores terminated session details.
     *
     * Verifies the event properties are correctly stored after
     * strict_types declaration was added.
     */
    public function test_event_stores_terminated_session_details(): void
    {
        // Arrange
        $user = User::factory()->create();
        $this->actingAs($user);

        // Act
        $event = new SessionTerminated(
            terminatedSessionId: 'sess-xyz-456',
            terminatedUserId: 99,
            terminatedUserName: 'John Doe',
        );

        // Assert: terminated details should be stored correctly
        $this->assertSame('sess-xyz-456', $event->terminatedSessionId);
        $this->assertSame(99, $event->terminatedUserId);
        $this->assertSame('John Doe', $event->terminatedUserName);
    }

    /**
     * Test event type is session.terminated.
     */
    public function test_event_type_is_session_terminated(): void
    {
        $this->assertSame('session.terminated', SessionTerminated::$eventType);
    }
}
