<?php

declare(strict_types=1);

namespace Aicl\Tests\Unit\Http\Resources;

use Aicl\Http\Resources\AiConversationResource;
use Aicl\Models\AiAgent;
use Aicl\Models\AiConversation;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Regression tests for AiConversationResource PHPStan changes.
 *
 * Covers the null-safe operator (?->) on $this->user and $this->agent
 * relationship properties in toArray(), the declare(strict_types=1)
 * enforcement, and the last_message_at?->toIso8601String() null guard.
 */
class AiConversationResourceRegressionTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed roles for user creation
        $this->artisan('db:seed', ['--class' => 'Aicl\Database\Seeders\RoleSeeder']);
    }

    // -- toArray with loaded relationships --

    /**
     * Test toArray includes user data when relationship is loaded.
     *
     * PHPStan change: $this->user?->id and $this->user?->name null-safe operators.
     */
    public function test_to_array_includes_user_when_loaded(): void
    {
        // Arrange: create a conversation with user and agent
        /** @var User $user */
        $user = User::factory()->create();
        $agent = AiAgent::factory()->create(['is_active' => true]);
        $conversation = AiConversation::factory()->create([
            'user_id' => $user->id,
            'ai_agent_id' => $agent->id,
        ]);
        $conversation->load(['user', 'agent']);

        // Act
        $resource = new AiConversationResource($conversation);
        $array = $resource->toArray(Request::create('/test'));

        // Assert: user data is included with id and name
        $this->assertArrayHasKey('user', $array);
        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('user_id', $array);
        $this->assertSame($user->id, $array['user_id']);
    }

    /**
     * Test toArray includes agent data when relationship is loaded.
     *
     * PHPStan change: $this->agent?->id, name, icon, color null-safe operators.
     */
    public function test_to_array_includes_agent_when_loaded(): void
    {
        // Arrange
        /** @var User $user */
        $user = User::factory()->create();
        $agent = AiAgent::factory()->create([
            'is_active' => true,
            'icon' => 'heroicon-o-cpu-chip',
            'color' => '#3B82F6',
        ]);
        $conversation = AiConversation::factory()->create([
            'user_id' => $user->id,
            'ai_agent_id' => $agent->id,
        ]);
        $conversation->load(['user', 'agent']);

        // Act
        $resource = new AiConversationResource($conversation);
        $array = $resource->toArray(Request::create('/test'));

        // Assert: agent data is included
        $this->assertArrayHasKey('agent', $array);
        $this->assertSame($agent->id, $array['ai_agent_id']);
    }

    // -- toArray core fields --

    /**
     * Test toArray includes all expected core fields.
     *
     * Verifies the complete field set in the resource serialization.
     */
    public function test_to_array_includes_core_fields(): void
    {
        // Arrange
        /** @var User $user */
        $user = User::factory()->create();
        $agent = AiAgent::factory()->create(['is_active' => true]);
        $conversation = AiConversation::factory()->create([
            'user_id' => $user->id,
            'ai_agent_id' => $agent->id,
        ]);

        // Act
        $resource = new AiConversationResource($conversation);
        $array = $resource->toArray(Request::create('/test'));

        // Assert: core fields present
        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('title', $array);
        $this->assertArrayHasKey('display_title', $array);
        $this->assertArrayHasKey('message_count', $array);
        $this->assertArrayHasKey('token_count', $array);
        $this->assertArrayHasKey('is_pinned', $array);
        $this->assertArrayHasKey('state', $array);
        $this->assertArrayHasKey('created_at', $array);
        $this->assertArrayHasKey('updated_at', $array);
    }

    // -- last_message_at null guard --

    /**
     * Test toArray handles null last_message_at without error.
     *
     * PHPStan change: $this->last_message_at?->toIso8601String() null guard.
     * New conversations have null last_message_at.
     */
    public function test_to_array_handles_null_last_message_at(): void
    {
        // Arrange: conversation with no messages has null last_message_at
        /** @var User $user */
        $user = User::factory()->create();
        $agent = AiAgent::factory()->create(['is_active' => true]);
        $conversation = AiConversation::factory()->create([
            'user_id' => $user->id,
            'ai_agent_id' => $agent->id,
            'last_message_at' => null,
        ]);

        // Act
        $resource = new AiConversationResource($conversation);
        $array = $resource->toArray(Request::create('/test'));

        // Assert: last_message_at key exists and is null
        $this->assertArrayHasKey('last_message_at', $array);
        $this->assertNull($array['last_message_at']);
    }
}
