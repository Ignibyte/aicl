<?php

declare(strict_types=1);

namespace Aicl\Tests\Unit\Http\Requests;

use Aicl\Http\Requests\StoreAiConversationRequest;
use Aicl\Models\AiAgent;
use Aicl\Models\AiConversation;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Regression tests for StoreAiConversationRequest PHPStan changes.
 *
 * Covers the authorize() change from `return true` to policy-based check,
 * the custom validation closure for ai_agent_id that verifies agent
 * existence AND is_active status in a single query, and the return type
 * annotation change from array<string, array<int, string>> to
 * array<string, array<int, mixed>>.
 */
class StoreAiConversationRequestRegressionTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed roles for policy authorization
        $this->artisan('db:seed', ['--class' => 'Aicl\Database\Seeders\RoleSeeder']);
    }

    // -- authorize with null user --

    /**
     * Test authorize returns false when no user is authenticated.
     *
     * PHPStan change: $this->user()?->can('create', AiConversation::class) ?? false.
     * Previously returned true unconditionally.
     */
    public function test_authorize_returns_false_when_unauthenticated(): void
    {
        // Arrange: create request without authentication
        $request = StoreAiConversationRequest::create('/api/ai-conversations', 'POST');
        $request->setUserResolver(fn () => null);

        // Act
        $result = $request->authorize();

        // Assert: unauthorized when no user
        $this->assertFalse($result);
    }

    // -- rules structure --

    /**
     * Test rules contains expected validation fields.
     *
     * Verifies the custom closure validator for ai_agent_id is present.
     */
    public function test_rules_contains_expected_fields(): void
    {
        // Arrange
        $request = new StoreAiConversationRequest;

        // Act
        $rules = $request->rules();

        // Assert: all expected fields present
        $this->assertArrayHasKey('title', $rules);
        $this->assertArrayHasKey('ai_agent_id', $rules);
        $this->assertArrayHasKey('context_page', $rules);
    }

    /**
     * Test ai_agent_id rules include uuid validation.
     *
     * The ai_agent_id field must be a required UUID.
     */
    public function test_ai_agent_id_requires_uuid(): void
    {
        // Arrange
        $request = new StoreAiConversationRequest;

        // Act
        $rules = $request->rules();

        // Assert: includes required and uuid validators
        $this->assertContains('required', $rules['ai_agent_id']);
        $this->assertContains('uuid', $rules['ai_agent_id']);
    }

    /**
     * Test ai_agent_id rules include a closure validator.
     *
     * PHPStan change: Custom closure validates agent existence AND is_active
     * status in a single query instead of separate exists rule + find().
     */
    public function test_ai_agent_id_has_custom_closure_validator(): void
    {
        // Arrange
        $request = new StoreAiConversationRequest;

        // Act
        $rules = $request->rules();
        $closureFound = false;

        // Check for a Closure in the ai_agent_id rules
        foreach ($rules['ai_agent_id'] as $rule) {
            if ($rule instanceof \Closure) {
                $closureFound = true;

                break;
            }
        }

        // Assert: custom closure validator is present
        $this->assertTrue($closureFound, 'ai_agent_id should have a custom closure validator');
    }

    /**
     * Test custom closure rejects non-existent agent ID.
     *
     * Edge case: UUID that doesn't match any agent should fail.
     */
    public function test_custom_closure_rejects_nonexistent_agent(): void
    {
        // Arrange
        $request = new StoreAiConversationRequest;
        $rules = $request->rules();
        $closure = null;

        foreach ($rules['ai_agent_id'] as $rule) {
            if ($rule instanceof \Closure) {
                $closure = $rule;

                break;
            }
        }

        $this->assertNotNull($closure);

        // Track whether fail was called
        $failMessage = null;
        $failCallback = function (string $message) use (&$failMessage): void {
            $failMessage = $message;
        };

        // Act: call with a non-existent UUID
        $closure('ai_agent_id', '00000000-0000-0000-0000-000000000000', $failCallback);

        // Assert: validation fails with "does not exist" message
        $this->assertNotNull($failMessage);
        $this->assertStringContainsString('does not exist', $failMessage);
    }

    /**
     * Test custom closure rejects inactive agent.
     *
     * Edge case: agent exists but is_active = false should fail.
     */
    public function test_custom_closure_rejects_inactive_agent(): void
    {
        // Arrange: create an inactive agent
        $agent = AiAgent::factory()->create(['is_active' => false]);
        $request = new StoreAiConversationRequest;
        $rules = $request->rules();
        $closure = null;

        foreach ($rules['ai_agent_id'] as $rule) {
            if ($rule instanceof \Closure) {
                $closure = $rule;

                break;
            }
        }

        $this->assertNotNull($closure);

        $failMessage = null;
        $failCallback = function (string $message) use (&$failMessage): void {
            $failMessage = $message;
        };

        // Act: call with the inactive agent's ID
        $closure('ai_agent_id', $agent->id, $failCallback);

        // Assert: validation fails with "not active" message
        $this->assertNotNull($failMessage);
        $this->assertStringContainsString('not active', $failMessage);
    }

    /**
     * Test custom closure accepts active agent.
     *
     * Happy path: active agent should pass validation.
     */
    public function test_custom_closure_accepts_active_agent(): void
    {
        // Arrange: create an active agent
        $agent = AiAgent::factory()->create(['is_active' => true]);
        $request = new StoreAiConversationRequest;
        $rules = $request->rules();
        $closure = null;

        foreach ($rules['ai_agent_id'] as $rule) {
            if ($rule instanceof \Closure) {
                $closure = $rule;

                break;
            }
        }

        $this->assertNotNull($closure);

        $failMessage = null;
        $failCallback = function (string $message) use (&$failMessage): void {
            $failMessage = $message;
        };

        // Act: call with the active agent's ID
        $closure('ai_agent_id', $agent->id, $failCallback);

        // Assert: no failure message — validation passed
        $this->assertNull($failMessage);
    }

    // -- title and context_page are nullable --

    /**
     * Test title field is nullable.
     */
    public function test_title_field_is_nullable(): void
    {
        // Arrange
        $request = new StoreAiConversationRequest;

        // Act
        $rules = $request->rules();

        // Assert
        $this->assertContains('nullable', $rules['title']);
    }
}
